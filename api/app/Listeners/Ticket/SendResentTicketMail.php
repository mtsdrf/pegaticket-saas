<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketResent;
use App\Models\Ticket\Ticket;
use App\Services\Ticket\TicketDeliveryService;
use Illuminate\Database\Eloquent\Collection;

class SendResentTicketMail
{
    public function __construct(
        private TicketDeliveryService $ticketDeliveryService,
    ) {
    }

    public function handle(TicketResent $event): void
    {
        $ticket = Ticket::query()
            ->with('saleItem.sale')
            ->where('uuid', $event->ticketUuid)
            ->whereNull('deleted_at')
            ->first();

        if (!$ticket || !$ticket->saleItem?->sale) {
            return;
        }

        $this->ticketDeliveryService->sendForSale(
            $ticket->saleItem->sale,
            new Collection([$ticket]),
            'resent',
        );
    }
}
