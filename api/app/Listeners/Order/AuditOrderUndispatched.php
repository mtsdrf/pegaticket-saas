<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderUndispatched;
use App\Models\AuditLog;

class AuditOrderUndispatched
{
    public function handle(OrderUndispatched $event): void
    {
        AuditLog::record(
            event: 'order_undispatched',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
