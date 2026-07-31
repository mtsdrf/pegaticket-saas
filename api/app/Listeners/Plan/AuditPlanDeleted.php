<?php

namespace App\Listeners\Plan;

use App\Events\Plan\PlanDeleted;
use App\Models\AuditLog;

class AuditPlanDeleted
{
    public function handle(PlanDeleted $event): void
    {
        AuditLog::record(
            event: 'plan_deleted',
            model: null,
            meta: ['plan_uuid' => $event->planUuid],
            actorId: $event->actorId
        );
    }
}
