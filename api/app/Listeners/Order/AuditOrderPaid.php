<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderPaid;
use App\Models\AuditLog;

class AuditOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        AuditLog::record(
            event: 'order_paid',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
