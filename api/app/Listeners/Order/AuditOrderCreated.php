<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCreated;
use App\Models\AuditLog;

class AuditOrderCreated
{
    public function handle(OrderCreated $event): void
    {
        AuditLog::record(
            event: 'order_created',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
