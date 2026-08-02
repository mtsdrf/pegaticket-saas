<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleCancelled;
use App\Models\AuditLog;

class AuditSaleCancelled
{
    public function handle(SaleCancelled $event): void
    {
        AuditLog::record(
            event: 'order_cancelled',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'cancellation_reason' => $event->cancellationReason,
            ],
            actorId: $event->actorId
        );
    }
}
