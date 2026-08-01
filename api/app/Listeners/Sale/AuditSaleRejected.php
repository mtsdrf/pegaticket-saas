<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleRejected;
use App\Models\AuditLog;

class AuditSaleRejected
{
    public function handle(SaleRejected $event): void
    {
        AuditLog::record(
            event: 'order_rejected',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'reason' => $event->reason,
            ],
            actorId: $event->actorId
        );
    }
}
