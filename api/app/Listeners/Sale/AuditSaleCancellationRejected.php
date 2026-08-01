<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCancellationRejected;
use App\Models\AuditLog;

class AuditSaleCancellationRejected
{
    public function handle(SaleCancellationRejected $event): void
    {
        AuditLog::record(
            event: 'order_cancellation_rejected',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
