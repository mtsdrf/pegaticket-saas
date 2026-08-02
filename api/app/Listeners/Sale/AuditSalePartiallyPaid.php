<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePartiallyPaid;
use App\Models\AuditLog;

class AuditSalePartiallyPaid
{
    public function handle(SalePartiallyPaid $event): void
    {
        AuditLog::record(
            event: 'order_partially_paid',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'amount' => $event->amount,
            ],
            actorId: $event->actorId
        );
    }
}
