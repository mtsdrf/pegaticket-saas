<?php

namespace App\Listeners\Webhook;

use App\Events\Order\OrderCreated;
use App\Models\Order\Order;
use App\Services\Webhook\WebhookDispatchService;

class DispatchWebhookOnOrderCreated
{
    public function __construct(private WebhookDispatchService $dispatchService)
    {
    }

    public function handle(OrderCreated $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->dispatchService->dispatchForOrder('order.created', $order);
    }
}
