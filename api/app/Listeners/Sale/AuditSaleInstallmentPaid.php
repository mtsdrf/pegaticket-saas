<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleInstallmentPaid;
use App\Models\AuditLog;

class AuditSaleInstallmentPaid
{
    public function handle(SaleInstallmentPaid $event): void
    {
        AuditLog::record(
            event: 'order_installment_paid',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
