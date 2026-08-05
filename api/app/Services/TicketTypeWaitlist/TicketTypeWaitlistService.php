<?php

namespace App\Services\TicketTypeWaitlist;

use App\DTOs\TicketTypeWaitlist\CreateTicketTypeWaitlistEntryDTO;
use App\Events\TicketTypeWaitlist\TicketTypeWaitlistEntryCreated;
use App\Mail\TicketTypeWaitlistAvailableMail;
use App\Models\Event\TicketType;
use App\Models\Event\TicketTypeWaitlistEntry;
use App\Services\Logging\ApplicationLogger;
use App\Services\Storefront\StorefrontHoldService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;

/**
 * Lista de espera de TicketType esgotado (roadmap inventário). Cadastro
 * público é idempotente por (ticket_type_id, email) — reenviar o mesmo
 * e-mail pro mesmo ticket_type não cria duplicata nem revela se já
 * existia (mesmo espírito de não vazar informação de RedeemGuestListEntry).
 *
 * Notificação roda via Command agendado (NotifyTicketTypeWaitlistCommand),
 * não via Event síncrono de "estoque voltou": disponibilidade é calculada
 * dinamicamente (StorefrontHoldService::availableQuantityForTicketType()),
 * não é uma coluna que muda — não existe um ponto único de mutação pra
 * acoplar um listener (pode subir tanto por reposição manual de lote
 * quanto por cancelamento de venda/expiração de hold). Varredura periódica
 * é o mesmo padrão já usado por SendRecompraNudgeMailsCommand.
 */
class TicketTypeWaitlistService
{
    public function __construct(
        private StorefrontHoldService $storefrontHoldService,
    ) {}

    public function create(int $tenantId, CreateTicketTypeWaitlistEntryDTO $dto): TicketTypeWaitlistEntry
    {
        $ticketType = TicketType::where('tenant_id', $tenantId)
            ->where('uuid', $dto->ticketTypeUuid)
            ->firstOrFail();

        $entry = TicketTypeWaitlistEntry::where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketType->id)
            ->where('email', $dto->email)
            ->first();

        if ($entry) {
            return $entry;
        }

        $entry = TicketTypeWaitlistEntry::create([
            'tenant_id' => $tenantId,
            'ticket_type_id' => $ticketType->id,
            'name' => $dto->name,
            'email' => $dto->email,
            'quantity_desired' => $dto->quantityDesired,
        ]);

        event(new TicketTypeWaitlistEntryCreated(
            ticketTypeWaitlistEntryUuid: $entry->uuid,
            ticketTypeUuid: $ticketType->uuid,
        ));

        return $entry;
    }

    public function paginate(int $tenantId, string $ticketTypeUuid, int $perPage = 15): LengthAwarePaginator
    {
        $ticketType = TicketType::where('tenant_id', $tenantId)
            ->where('uuid', $ticketTypeUuid)
            ->firstOrFail();

        return TicketTypeWaitlistEntry::where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketType->id)
            ->whereNull('deleted_at')
            ->with('ticketType')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Varredura de NotifyTicketTypeWaitlistCommand — só os ticket_types com
     * inscrito pendente (notified_at IS NULL) entram na conta de
     * disponibilidade, evitando calcular à toa pra todo TicketType do
     * tenant.
     */
    public function notifyAvailableTicketTypes(): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $ticketTypeIds = TicketTypeWaitlistEntry::whereNull('deleted_at')
            ->whereNull('notified_at')
            ->distinct()
            ->pluck('ticket_type_id');

        foreach ($ticketTypeIds as $ticketTypeId) {
            $ticketType = TicketType::with(['event', 'batches'])->find($ticketTypeId);

            if (! $ticketType || ! $ticketType->event) {
                $skipped++;

                continue;
            }

            $available = $this->storefrontHoldService->availableQuantityForTicketType($ticketType);

            if ($available <= 0) {
                continue;
            }

            $entries = TicketTypeWaitlistEntry::where('ticket_type_id', $ticketType->id)
                ->whereNull('deleted_at')
                ->whereNull('notified_at')
                ->get();

            foreach ($entries as $entry) {
                try {
                    Mail::to($entry->email)->send(new TicketTypeWaitlistAvailableMail(
                        ticketType: $ticketType,
                        waitlistEntry: $entry,
                    ));

                    $entry->forceFill(['notified_at' => now()])->save();
                    $sent++;
                } catch (\Throwable $e) {
                    $failed++;

                    ApplicationLogger::error('Falha ao enviar e-mail de lista de espera', [
                        'ticket_type_waitlist_entry_uuid' => $entry->uuid,
                        'exception_class' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }
}
