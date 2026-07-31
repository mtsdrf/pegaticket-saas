<?php

namespace App\Listeners\Storefront;

use App\Events\Storefront\ReactivationRuleUpdated;
use App\Models\AuditLog;

class AuditReactivationRuleUpdated
{
    public function handle(ReactivationRuleUpdated $event): void
    {
        AuditLog::record(
            event: 'reactivation_rule_updated',
            model: null,
            meta: ['tenant_id' => $event->tenantId],
            actorId: $event->actorId
        );
    }
}
