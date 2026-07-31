<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderInstallmentCreated;
use App\Models\AuditLog;

class AuditOrderInstallmentCreated
{
    public function handle(OrderInstallmentCreated $event): void
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
