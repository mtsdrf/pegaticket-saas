<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCancellationApproved;
use App\Models\AuditLog;

class AuditSaleCancellationApproved
{
    public function handle(SaleCancellationApproved $event): void
    {
        AuditLog::record(
            event: 'order_cancellation_approved',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
            ],
            actorId: $event->actorId
        );
    }
}
