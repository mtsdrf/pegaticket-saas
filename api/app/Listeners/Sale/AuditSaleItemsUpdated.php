<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleItemsUpdated;
use App\Models\AuditLog;

class AuditSaleItemsUpdated
{
    public function handle(SaleItemsUpdated $event): void
    {
        AuditLog::record(
            event: 'order_items_updated',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
