<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleInstallmentCreated;
use App\Models\AuditLog;

class AuditSaleInstallmentCreated
{
    public function handle(SaleInstallmentCreated $event): void
    {
        AuditLog::record(
            event: 'order_installment_created',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
