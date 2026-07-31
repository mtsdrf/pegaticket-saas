<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCancellationRequested;
use App\Models\AuditLog;

class AuditOrderCancellationRequested
{
    public function handle(OrderCancellationRequested $event): void
    {
        AuditLog::record(
            event: 'order_cancellation_requested',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'reason' => $event->reason,
                'final_customer_uuid' => $event->finalCustomerUuid,
            ],
            actorId: null
        );
    }
}
