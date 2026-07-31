<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockLocationDeleted;
use App\Models\AuditLog;

class AuditStockLocationDeleted
{
    public function handle(StockLocationDeleted $event): void
    {
        AuditLog::record(
            event: 'stock_location_deleted',
            model: null,
            meta: ['stock_location_uuid' => $event->stockLocationUuid],
            actorId: $event->actorId
        );
    }
}
