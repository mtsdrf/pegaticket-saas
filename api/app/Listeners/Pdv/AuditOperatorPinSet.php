<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\OperatorPinSet;
use App\Models\AuditLog;

class AuditOperatorPinSet
{
    public function handle(OperatorPinSet $event): void
    {
        AuditLog::record(
            event: 'operator_pin_set',
            model: null,
            meta: [
                'tenant_uuid' => $event->tenantUuid,
                'user_uuid' => $event->userUuid,
            ],
            actorId: $event->actorId
        );
    }
}
