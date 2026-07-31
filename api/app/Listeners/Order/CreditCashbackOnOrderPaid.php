<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderPaid;
use App\Models\Order\Order;
use App\Services\Storefront\CashbackService;

/**
 * Listener fino — toda a regra de negócio (guards, cálculo, criação do
 * lote) vive em CashbackService::creditEarning(). Roda ainda dentro da
 * transação de OrderService::pay() (event() é síncrono).
 */
class CreditCashbackOnOrderPaid
{
    public function __construct(
        private CashbackService $cashbackService,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->cashbackService->creditEarning($order);
    }
}
