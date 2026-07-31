<?php

namespace App\Listeners\Webhook;

use App\Events\Order\OrderRejected;
use App\Models\Order\Order;
use App\Services\Webhook\WebhookDispatchService;

class DispatchWebhookOnOrderRejected
{
    public function __construct(private WebhookDispatchService $dispatchService)
    {
    }

    public function handle(OrderRejected $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->dispatchService->dispatchForOrder('order.rejected', $order);
    }
}
