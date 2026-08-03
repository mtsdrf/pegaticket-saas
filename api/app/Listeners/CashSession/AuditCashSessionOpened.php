<?php

namespace App\Listeners\CashSession;

use App\Events\CashSession\CashSessionOpened;
use App\Models\AuditLog;

class AuditCashSessionOpened
{
    public function handle(CashSessionOpened $event): void
    {
        AuditLog::record(
            event: 'cash_session_opened',
            model: null,
            meta: [
                'cash_session_uuid' => $event->cashSessionUuid,
                'opening_amount' => $event->openingAmount,
            ],
            actorId: $event->actorId
        );
    }
}
