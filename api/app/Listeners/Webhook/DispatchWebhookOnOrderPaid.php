<?php

namespace App\Listeners\Webhook;

use App\Events\Order\OrderPaid;
use App\Models\Order\Order;
use App\Services\Webhook\WebhookDispatchService;

class DispatchWebhookOnOrderPaid
{
    public function __construct(private WebhookDispatchService $dispatchService)
    {
    }

    public function handle(OrderPaid $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->dispatchService->dispatchForOrder('order.paid', $order);
    }
}
