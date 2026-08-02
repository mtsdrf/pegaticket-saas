<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleReopened;
use App\Models\AuditLog;

class AuditSaleReopened
{
    public function handle(SaleReopened $event): void
    {
        AuditLog::record(
            event: 'order_reopened',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
