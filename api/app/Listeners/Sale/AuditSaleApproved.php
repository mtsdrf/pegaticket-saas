<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleApproved;
use App\Models\AuditLog;

class AuditSaleApproved
{
    public function handle(SaleApproved $event): void
    {
        AuditLog::record(
            event: 'order_approved',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
