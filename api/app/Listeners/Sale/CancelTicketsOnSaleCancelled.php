<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCancelled;
use App\Models\Sale\Sale;
use App\Services\Ticket\TicketIssuanceService;

class CancelTicketsOnSaleCancelled
{
    public function __construct(
        private TicketIssuanceService $ticketIssuanceService,
    ) {
    }

    public function handle(SaleCancelled $event): void
    {
        $order = Sale::where('uuid', $event->saleUuid)->whereNull('deleted_at')->first();

        if (!$order) {
            return;
        }

        $this->ticketIssuanceService->cancelForSale($order, $event->actorId);
    }
}
