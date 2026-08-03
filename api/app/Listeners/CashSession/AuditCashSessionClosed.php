<?php

namespace App\Listeners\CashSession;

use App\Events\CashSession\CashSessionClosed;
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
                'closing_amount' => $event->closingAmount,
                'expected_cash_amount' => $event->expectedCashAmount,
                'difference_amount' => $event->differenceAmount,
            ],
            actorId: $event->actorId
        );
    }
}
