<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderOutForDelivery;
use App\Models\AuditLog;

class AuditOrderOutForDelivery
{
    public function handle(OrderOutForDelivery $event): void
    {
        AuditLog::record(
            event: 'order_out_for_delivery',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
