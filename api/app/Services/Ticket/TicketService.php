<?php

namespace App\Services\Ticket;

use App\Events\Ticket\TicketResent;
use App\Models\Ticket\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * Leitura/reenvio de Ticket — sem create()/update() próprios: emissão é
 * 100% responsabilidade de TicketIssuanceService, disparada pelo ciclo de
 * vida da Sale.
 */
class TicketService
{
    public const EAGER_RELATIONS = ['ticketType.event', 'ticketType.session', 'seat', 'orderItem.order'];

    public function __construct(
        private TicketRepositoryInterface $repository
    ) {
    }

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

        if (!empty($filters['status'])) {
            $query->where('tickets.status', $filters['status']);
        }

        if (!empty($filters['ticket_type_uuid'])) {
            $query->whereHas('ticketType', fn($q) => $q->where('uuid', $filters['ticket_type_uuid']));
        }

        if (!empty($filters['event_uuid'])) {
            $query->whereHas('ticketType.event', fn($q) => $q->where('uuid', $filters['event_uuid']));
        }

        if (!empty($filters['event_session_uuid'])) {
            $query->whereHas('ticketType.session', fn($q) => $q->where('uuid', $filters['event_session_uuid']));
        }

        if (!empty($filters['sale_uuid'])) {
            $query->whereHas('orderItem.order', fn($q) => $q->where('uuid', $filters['sale_uuid']));
        }

        if (!empty($filters['search'])) {
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
     * "Reenvio pelo administrador" (spec 5.15). Nesta rodada não há
     * integração de e-mail/PDF real ainda — só registra a intenção
     * (evento + auditoria) para o frontend confirmar a ação; o disparo
     * efetivo de e-mail fica como pendência sinalizada, não fabricada como
     * concluída.
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

    private function assertBelongsToCurrentTenant(Ticket $ticket): void
    {
        if ((int) $ticket->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
