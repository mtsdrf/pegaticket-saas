<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCancellationApproved;
use App\Models\AuditLog;

class AuditOrderCancellationApproved
{
    public function handle(OrderCancellationApproved $event): void
    {
        AuditLog::record(
            event: 'order_cancellation_approved',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
