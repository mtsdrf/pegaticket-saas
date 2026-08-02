<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleInstallmentUnpaid;
use App\Models\AuditLog;

class AuditSaleInstallmentUnpaid
{
    public function handle(SaleInstallmentUnpaid $event): void
    {
        AuditLog::record(
            event: 'order_installment_unpaid',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
