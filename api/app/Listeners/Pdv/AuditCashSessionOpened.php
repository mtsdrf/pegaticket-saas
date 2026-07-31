<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\CashSessionOpened;
use App\Models\AuditLog;

class AuditCashSessionOpened
{
    public function handle(CashSessionOpened $event): void
    {
        AuditLog::record(
            event: 'cash_session_opened',
            model: null,
            meta: ['cash_session_uuid' => $event->cashSessionUuid],
            actorId: $event->actorId
        );
    }
}
