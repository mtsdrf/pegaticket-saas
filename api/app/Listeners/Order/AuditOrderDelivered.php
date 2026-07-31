<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderDelivered;
use App\Models\AuditLog;

class AuditOrderDelivered
{
    public function handle(OrderDelivered $event): void
    {
        AuditLog::record(
            event: 'order_delivered',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
