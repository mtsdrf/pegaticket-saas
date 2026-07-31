<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderUnpaid;
use App\Models\Order\Order;
use App\Services\Storefront\CashbackService;

class ReverseCashbackOnOrderUnpaid
{
    public function __construct(
        private CashbackService $cashbackService,
    ) {
    }

    public function handle(OrderUnpaid $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->cashbackService->reverseEarning($order);
    }
}
