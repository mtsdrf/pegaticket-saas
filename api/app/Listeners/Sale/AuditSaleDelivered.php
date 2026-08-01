<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleDelivered;
use App\Models\AuditLog;

class AuditSaleDelivered
{
    public function handle(SaleDelivered $event): void
    {
        AuditLog::record(
            event: 'order_delivered',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
