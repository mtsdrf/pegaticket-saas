<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCompleted;
use App\Models\AuditLog;

class AuditSaleCompleted
{
    public function handle(SaleCompleted $event): void
    {
        AuditLog::record(
            event: 'order_completed',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
