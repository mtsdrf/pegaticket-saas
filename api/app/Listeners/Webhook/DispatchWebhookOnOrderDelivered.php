<?php

namespace App\Listeners\Webhook;

use App\Events\Order\OrderDelivered;
use App\Models\Order\Order;
use App\Services\Webhook\WebhookDispatchService;

class DispatchWebhookOnOrderDelivered
{
    public function __construct(private WebhookDispatchService $dispatchService)
    {
    }

    public function handle(OrderDelivered $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->dispatchService->dispatchForOrder('order.delivered', $order);
    }
}
