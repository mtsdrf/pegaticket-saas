<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleInstallmentDeleted;
use App\Models\AuditLog;

class AuditSaleInstallmentDeleted
{
    public function handle(SaleInstallmentDeleted $event): void
    {
        AuditLog::record(
            event: 'order_installment_deleted',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
