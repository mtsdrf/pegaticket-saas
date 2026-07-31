<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockLocationUpdated;
use App\Models\AuditLog;

class AuditStockLocationUpdated
{
    public function handle(StockLocationUpdated $event): void
    {
        AuditLog::record(
            event: 'stock_location_updated',
            model: null,
            meta: [
                'stock_location_uuid' => $event->stockLocationUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
