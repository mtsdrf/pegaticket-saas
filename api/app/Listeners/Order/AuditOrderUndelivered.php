<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderUndelivered;
use App\Models\AuditLog;

class AuditOrderUndelivered
{
    public function handle(OrderUndelivered $event): void
    {
        AuditLog::record(
            event: 'order_undelivered',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
