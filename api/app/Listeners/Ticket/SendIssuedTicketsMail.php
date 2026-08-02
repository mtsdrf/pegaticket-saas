<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketsIssued;
use App\Models\Sale\Sale;
use App\Models\Ticket\Ticket;
use App\Services\Ticket\TicketDeliveryService;

class SendIssuedTicketsMail
{
    public function __construct(
        private TicketDeliveryService $ticketDeliveryService,
    ) {
    }

    public function handle(TicketsIssued $event): void
    {
        $sale = Sale::where('uuid', $event->saleUuid)
            ->whereNull('deleted_at')
            ->first();

        if (!$sale) {
            return;
        }

        $tickets = Ticket::query()
            ->whereIn('uuid', $event->ticketUuids)
            ->where('tenant_id', $sale->tenant_id)
            ->whereNull('deleted_at')
            ->get();

        $this->ticketDeliveryService->sendForSale($sale, $tickets, 'issued');
    }
}
