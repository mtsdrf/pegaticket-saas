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
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
