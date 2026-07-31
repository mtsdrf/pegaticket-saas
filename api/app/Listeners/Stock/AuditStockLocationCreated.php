<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockLocationCreated;
use App\Models\AuditLog;

class AuditStockLocationCreated
{
    public function handle(StockLocationCreated $event): void
    {
        AuditLog::record(
            event: 'stock_location_created',
            model: null,
            meta: ['stock_location_uuid' => $event->stockLocationUuid],
            actorId: $event->actorId
        );
    }
}
