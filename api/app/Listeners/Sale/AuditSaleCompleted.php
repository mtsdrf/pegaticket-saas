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
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
