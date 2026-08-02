<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaid;
use App\Models\AuditLog;

class AuditSalePaid
{
    public function handle(SalePaid $event): void
    {
        AuditLog::record(
            event: 'order_paid',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
            ],
            actorId: $event->actorId
        );
    }
}
