<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderUnpaid;
use App\Models\AuditLog;

class AuditOrderUnpaid
{
    public function handle(OrderUnpaid $event): void
    {
        AuditLog::record(
            event: 'order_unpaid',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
