<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleItemsUpdated;
use App\Models\AuditLog;

class AuditSaleItemsUpdated
{
    public function handle(SaleItemsUpdated $event): void
    {
        AuditLog::record(
            event: 'sale_items_updated',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
