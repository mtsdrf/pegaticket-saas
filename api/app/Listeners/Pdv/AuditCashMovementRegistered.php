<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\CashMovementRegistered;
use App\Models\AuditLog;

class AuditCashMovementRegistered
{
    public function handle(CashMovementRegistered $event): void
    {
        AuditLog::record(
            event: 'cash_movement_registered',
            model: null,
            meta: [
                'cash_session_uuid' => $event->cashSessionUuid,
                'cash_movement_uuid' => $event->cashMovementUuid,
                'type' => $event->type,
                'amount' => $event->amount,
            ],
            actorId: $event->actorId
        );
    }
}
