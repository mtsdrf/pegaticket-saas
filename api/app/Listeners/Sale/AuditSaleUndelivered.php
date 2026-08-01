<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleUndelivered;
use App\Models\AuditLog;

class AuditSaleUndelivered
{
    public function handle(SaleUndelivered $event): void
    {
        AuditLog::record(
            event: 'order_undelivered',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
