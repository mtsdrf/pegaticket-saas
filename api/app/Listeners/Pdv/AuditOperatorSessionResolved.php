<?php

namespace App\Listeners\Pdv;

use App\Events\Pdv\OperatorSessionResolved;
use App\Models\AuditLog;

class AuditOperatorSessionResolved
{
    public function handle(OperatorSessionResolved $event): void
    {
        AuditLog::record(
            event: 'operator_session_resolved',
            model: null,
            meta: [
                'tenant_uuid' => $event->tenantUuid,
                'operator_uuid' => $event->operatorUuid,
            ],
            actorId: $event->actorId
        );
    }
}
