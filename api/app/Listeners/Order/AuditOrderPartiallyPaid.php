<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderPartiallyPaid;
use App\Models\AuditLog;

class AuditOrderPartiallyPaid
{
    public function handle(OrderPartiallyPaid $event): void
    {
        AuditLog::record(
            event: 'order_partially_paid',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'amount' => $event->amount,
            ],
            actorId: $event->actorId
        );
    }
}
