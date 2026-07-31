<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderApproved;
use App\Models\AuditLog;

class AuditOrderApproved
{
    public function handle(OrderApproved $event): void
    {
        AuditLog::record(
            event: 'order_approved',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
