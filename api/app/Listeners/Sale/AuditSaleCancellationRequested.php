<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCancellationRequested;
use App\Models\AuditLog;

class AuditSaleCancellationRequested
{
    public function handle(SaleCancellationRequested $event): void
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
