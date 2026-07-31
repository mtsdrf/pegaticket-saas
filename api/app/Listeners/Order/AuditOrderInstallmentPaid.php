<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderInstallmentPaid;
use App\Models\AuditLog;

class AuditOrderInstallmentPaid
{
    public function handle(OrderInstallmentPaid $event): void
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
