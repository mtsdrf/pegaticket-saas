<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\CashSessionClosed;
use App\Models\AuditLog;

class AuditCashSessionClosed
{
    public function handle(CashSessionClosed $event): void
    {
        AuditLog::record(
            event: 'cash_session_closed',
            model: null,
            meta: [
                'cash_session_uuid' => $event->cashSessionUuid,
                'difference' => $event->difference,
            ],
            actorId: $event->actorId
        );
    }
}
