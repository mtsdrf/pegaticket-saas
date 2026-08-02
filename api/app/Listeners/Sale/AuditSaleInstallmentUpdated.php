<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleInstallmentUpdated;
use App\Models\AuditLog;

class AuditSaleInstallmentUpdated
{
    public function handle(SaleInstallmentUpdated $event): void
    {
        AuditLog::record(
            event: 'order_installment_updated',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'installment_uuid' => $event->installmentUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
