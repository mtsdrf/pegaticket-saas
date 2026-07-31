<?php

namespace App\Listeners\Plan;

use App\Events\Plan\PlanUpdated;
use App\Models\AuditLog;

class AuditPlanUpdated
{
    public function handle(PlanUpdated $event): void
    {
        AuditLog::record(
            event: 'plan_updated',
            model: null,
            meta: [
                'plan_uuid' => $event->planUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
