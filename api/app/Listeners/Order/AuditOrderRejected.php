<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderRejected;
use App\Models\AuditLog;

class AuditOrderRejected
{
    public function handle(OrderRejected $event): void
    {
        AuditLog::record(
            event: 'order_rejected',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'reason' => $event->reason,
            ],
            actorId: $event->actorId
        );
    }
}
