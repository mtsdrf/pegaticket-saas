<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaid;
use App\Models\Sale\Sale;
use App\Services\Ticket\TicketIssuanceService;

/**
 * Ponto único de emissão automática de ingresso (roadmap 2.8/TicketIssuanceService):
 * SalePaid é disparado tanto por SaleService::performPayment() (pay(),
 * create() com mark_as_paid, cascata de payInstallment()) quanto por
 * SalePaymentService::markOrderPaid() (confirmação via webhook/Pix) — os
 * dois únicos caminhos que marcam `orders.is_paid = true` no sistema.
 * Ouvir aqui em vez de chamar TicketIssuanceService direto de dentro de
 * SaleService cobre os dois sem duplicar a chamada em cada um.
 */
class IssueTicketsOnSalePaid
{
    public function __construct(
        private TicketIssuanceService $ticketIssuanceService,
    ) {
    }

    public function handle(SalePaid $event): void
    {
        $order = Sale::where('uuid', $event->orderUuid)->whereNull('deleted_at')->first();

        if (!$order) {
            return;
        }

        $this->ticketIssuanceService->issueForSale($order, $event->actorId);
    }
}
