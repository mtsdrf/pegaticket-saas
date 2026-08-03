<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketTransferred;
use App\Models\Ticket\Ticket;
use App\Services\Ticket\TicketDeliveryService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Confirma a transferência para o e-mail do COMPRADOR (dono da venda) —
 * o participante recebe o QR quando o comprador repassa manualmente
 * (mesmo modelo de "Meus ingressos" hoje, sem conta própria por
 * participante).
 */
class SendTransferredTicketMail
{
    public function __construct(
        private TicketDeliveryService $ticketDeliveryService,
    ) {}

    public function handle(TicketTransferred $event): void
    {
        $ticket = Ticket::query()
            ->with('saleItem.sale')
            ->where('uuid', $event->ticketUuid)
            ->whereNull('deleted_at')
            ->first();

        if (! $ticket || ! $ticket->saleItem?->sale) {
            return;
        }

        $this->ticketDeliveryService->sendForSale(
            $ticket->saleItem->sale,
            new Collection([$ticket]),
            'transferred',
        );
    }
}
