<?php

namespace App\Services\Ticket;

use App\DTOs\Ticket\TransferTicketDTO;
use App\Events\Ticket\TicketResent;
use App\Events\Ticket\TicketTransferred;
use App\Exceptions\InvalidTicketStateException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Leitura/reenvio de Ticket — sem create()/update() próprios: emissão é
 * 100% responsabilidade de TicketIssuanceService, disparada pelo ciclo de
 * vida da Sale.
 */
class TicketService
{
    public const EAGER_RELATIONS = ['ticketType.event', 'ticketType.session', 'seat', 'saleItem.sale'];

    public const CHECKIN_GRANTED_RESULTS = ['valido', 'reentrada_autorizada'];

    public const CHECKIN_WARNING_RESULTS = ['ja_utilizado', 'reentrada_limite_excedido', 'reentrada_intervalo_nao_atingido'];

    public function __construct(
        private TicketRepositoryInterface $repository
    ) {}

    public function find(Ticket $ticket): Ticket
    {
        $this->assertBelongsToCurrentTenant($ticket);

        return $ticket;
    }

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = Ticket::query()
            ->where('tickets.tenant_id', $tenantId)
            ->whereNull('tickets.deleted_at')
            ->with(self::EAGER_RELATIONS);

        if (! empty($filters['status'])) {
            $query->where('tickets.status', $filters['status']);
        }

        if (! empty($filters['ticket_type_uuid'])) {
            $query->whereHas('ticketType', fn ($q) => $q->where('uuid', $filters['ticket_type_uuid']));
        }

        if (! empty($filters['event_uuid'])) {
            $query->whereHas('ticketType.event', fn ($q) => $q->where('uuid', $filters['event_uuid']));
        }

        if (! empty($filters['event_session_uuid'])) {
            $query->whereHas('ticketType.session', fn ($q) => $q->where('uuid', $filters['event_session_uuid']));
        }

        if (! empty($filters['sale_uuid'])) {
            $query->whereHas('saleItem.sale', fn ($q) => $q->where('uuid', $filters['sale_uuid']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('tickets.code', 'like', "%{$search}%")
                    ->orWhere('tickets.attendee_name', 'like', "%{$search}%")
                    ->orWhere('tickets.attendee_document', 'like', "%{$search}%");
            });
        }

        $sortColumn = in_array($sortBy, ['status', 'issued_at', 'created_at'], true) ? "tickets.{$sortBy}" : 'tickets.created_at';
        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortColumn, $dir)->paginate($perPage);
    }

    /**
     * "Reenvio pelo administrador" (spec 5.15). O listener do evento faz
     * o envio real do e-mail do ingresso quando houver comprador com
     * e-mail cadastrado; aqui o service só valida escopo e dispara o
     * evento de domínio.
     */
    public function resend(Ticket $ticket): Ticket
    {
        $this->assertBelongsToCurrentTenant($ticket);

        event(new TicketResent(
            ticketUuid: $ticket->uuid,
            actorId: (int) Auth::id()
        ));

        return $ticket;
    }

    /**
     * "Titularidade e transferência" (roadmap Fase 4, autosserviço via
     * Portal): só ingresso `ativo` (não usado, não cancelado/estornado/
     * bloqueado) pode trocar de participante. code/qr_token são rotacionados
     * — quem tinha o QR antigo salvo/printado não consegue mais usá-lo.
     */
    public function transfer(Ticket $ticket, TransferTicketDTO $dto, ?string $finalCustomerUuid = null): Ticket
    {
        $this->assertBelongsToCurrentTenant($ticket);

        return DB::transaction(function () use ($ticket, $dto, $finalCustomerUuid) {
            // Re-lê com lockForUpdate() dentro da transação: o objeto
            // recebido pode ter sido carregado antes do lock (ex: via route
            // model binding), então a checagem de status precisa ser contra
            // a linha travada, não contra o estado em memória — duas
            // transferências concorrentes pro mesmo ticket (duplo-clique,
            // replay) não podem ambas passar no check-then-act.
            $locked = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'ativo') {
                throw new InvalidTicketStateException(__('messages.ticket.not_transferable'));
            }

            $previousAttendeeName = $locked->attendee_name;

            $locked->attendee_name = $dto->attendeeName;
            $locked->attendee_document = $dto->attendeeDocument;
            $locked->rotateAccessCredentials();
            $locked->save();

            $ticket = $locked;

            event(new TicketTransferred(
                ticketUuid: $ticket->uuid,
                previousAttendeeName: $previousAttendeeName,
                newAttendeeName: $dto->attendeeName,
                finalCustomerUuid: $finalCustomerUuid,
            ));

            return $ticket;
        });
    }

    public function checkinHistory(Ticket $ticket): Collection
    {
        $this->assertBelongsToCurrentTenant($ticket);

        return $ticket->checkins()
            ->with('operator')
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->get();
    }

    public function checkinOperationalSummary(int $tenantId, array $filters = [], int $limit = 8): array
    {
        $baseQuery = TicketCheckin::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when(
                ! empty($filters['gate_name']),
                fn ($query) => $query->where('gate_name', trim((string) $filters['gate_name']))
            )
            ->when(
                ! empty($filters['event_uuid']),
                fn ($query) => $query->whereHas('ticket.ticketType.event', fn ($eventQuery) => $eventQuery->where('uuid', $filters['event_uuid']))
            )
            ->when(
                ! empty($filters['event_session_uuid']),
                fn ($query) => $query->whereHas('ticket.ticketType.session', fn ($sessionQuery) => $sessionQuery->where('uuid', $filters['event_session_uuid']))
            );

        $total = (clone $baseQuery)->count();
        $granted = (clone $baseQuery)->whereIn('result', self::CHECKIN_GRANTED_RESULTS)->count();
        $warning = (clone $baseQuery)->whereIn('result', self::CHECKIN_WARNING_RESULTS)->count();

        $recent = (clone $baseQuery)
            ->with(['operator', 'ticket.ticketType.event', 'ticket.ticketType.session'])
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'filters' => [
                'event_uuid' => $filters['event_uuid'] ?? null,
                'event_session_uuid' => $filters['event_session_uuid'] ?? null,
                'gate_name' => $filters['gate_name'] ?? null,
            ],
            'counters' => [
                'total' => $total,
                'granted' => $granted,
                'warning' => $warning,
                'blocked' => max(0, $total - $granted - $warning),
            ],
            'recent' => $recent,
        ];
    }

    private function assertBelongsToCurrentTenant(Ticket $ticket): void
    {
        if ((int) $ticket->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
