<?php

namespace App\Listeners\Plan;

use App\Events\Plan\PlanFunctionalitiesSynced;
use App\Models\AuditLog;

class AuditPlanFunctionalitiesSynced
{
    public function handle(PlanFunctionalitiesSynced $event): void
    {
        AuditLog::record(
            event: 'plan_functionalities_synced',
            model: null,
            meta: ['plan_uuid' => $event->planUuid],
            actorId: $event->actorId
        );
    }
}
