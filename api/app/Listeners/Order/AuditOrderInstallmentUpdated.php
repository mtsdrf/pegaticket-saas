<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderInstallmentUpdated;
use App\Models\AuditLog;

class AuditOrderInstallmentUpdated
{
    public function handle(OrderInstallmentUpdated $event): void
    {
        AuditLog::record(
            event: 'order_installment_updated',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
