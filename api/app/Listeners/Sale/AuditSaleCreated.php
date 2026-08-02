<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCreated;
use App\Models\AuditLog;

class AuditSaleCreated
{
    public function handle(SaleCreated $event): void
    {
        AuditLog::record(
            event: 'order_created',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
