<?php

namespace App\Listeners\Plan;

use App\Events\Plan\PlanCreated;
use App\Models\AuditLog;

class AuditPlanCreated
{
    public function handle(PlanCreated $event): void
    {
        AuditLog::record(
            event: 'plan_created',
            model: null,
            meta: ['plan_uuid' => $event->planUuid],
            actorId: $event->actorId
        );
    }
}
