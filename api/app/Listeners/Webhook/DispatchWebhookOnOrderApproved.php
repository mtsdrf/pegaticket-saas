<?php

namespace App\Listeners\Webhook;

use App\Events\Order\OrderApproved;
use App\Models\Order\Order;
use App\Services\Webhook\WebhookDispatchService;

class DispatchWebhookOnOrderApproved
{
    public function __construct(private WebhookDispatchService $dispatchService)
    {
    }

    public function handle(OrderApproved $event): void
    {
        $order = Order::where('uuid', $event->orderUuid)->first();

        if (!$order) {
            return;
        }

        $this->dispatchService->dispatchForOrder('order.approved', $order);
    }
}
