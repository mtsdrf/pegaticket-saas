<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCancellationRejected;
use App\Models\AuditLog;

class AuditOrderCancellationRejected
{
    public function handle(OrderCancellationRejected $event): void
    {
        AuditLog::record(
            event: 'order_cancellation_rejected',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
