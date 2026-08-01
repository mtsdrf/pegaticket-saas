<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleOutForDelivery;
use App\Models\AuditLog;

class AuditSaleOutForDelivery
{
    public function handle(SaleOutForDelivery $event): void
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
