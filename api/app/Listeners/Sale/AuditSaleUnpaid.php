<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleUnpaid;
use App\Models\AuditLog;

class AuditSaleUnpaid
{
    public function handle(SaleUnpaid $event): void
    {
        AuditLog::record(
            event: 'order_unpaid',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
