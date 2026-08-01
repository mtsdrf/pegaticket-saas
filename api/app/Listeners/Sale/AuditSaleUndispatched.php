<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleUndispatched;
use App\Models\AuditLog;

class AuditSaleUndispatched
{
    public function handle(SaleUndispatched $event): void
    {
        AuditLog::record(
            event: 'order_undispatched',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
