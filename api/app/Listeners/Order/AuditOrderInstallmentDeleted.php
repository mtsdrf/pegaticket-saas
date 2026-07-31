<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderInstallmentDeleted;
use App\Models\AuditLog;

class AuditOrderInstallmentDeleted
{
    public function handle(OrderInstallmentDeleted $event): void
    {
        AuditLog::record(
            event: 'order_installment_deleted',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
            ],
            actorId: $event->actorId
        );
    }
}
