<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderInstallmentUnpaid;
use App\Models\AuditLog;

class AuditOrderInstallmentUnpaid
{
    public function handle(OrderInstallmentUnpaid $event): void
    {
        AuditLog::record(
            event: 'order_installment_unpaid',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
