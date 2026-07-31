<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderItemsUpdated;
use App\Models\AuditLog;

class AuditOrderItemsUpdated
{
    public function handle(OrderItemsUpdated $event): void
    {
        AuditLog::record(
            event: 'order_items_updated',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
