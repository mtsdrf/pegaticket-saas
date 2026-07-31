<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\PdvSaleCompleted;
use App\Models\AuditLog;

class AuditPdvSaleCompleted
{
    public function handle(PdvSaleCompleted $event): void
    {
        AuditLog::record(
            event: 'pdv_sale_completed',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'cash_session_uuid' => $event->cashSessionUuid,
                'payments' => $event->payments,
            ],
            actorId: $event->actorId
        );
    }
}
