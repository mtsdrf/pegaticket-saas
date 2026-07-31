<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantFeatureOverridesSynced;
use App\Models\AuditLog;

class AuditTenantFeatureOverridesSynced
{
    public function handle(TenantFeatureOverridesSynced $event): void
    {
        AuditLog::record(
            event: 'tenant_feature_overrides_synced',
            model: null,
            meta: ['tenant_id' => $event->tenantId],
            actorId: $event->actorId
        );
    }
}
