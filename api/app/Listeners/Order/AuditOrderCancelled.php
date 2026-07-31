<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCancelled;
use App\Models\AuditLog;

class AuditOrderCancelled
{
    public function handle(OrderCancelled $event): void
    {
        AuditLog::record(
            event: 'order_cancelled',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'cancellation_reason' => $event->cancellationReason,
            ],
            actorId: $event->actorId
        );
    }
}
