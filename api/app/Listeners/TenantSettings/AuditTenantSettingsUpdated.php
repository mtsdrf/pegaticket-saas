<?php

namespace App\Listeners\TenantSettings;

use App\Events\TenantSettings\TenantSettingsUpdated;
use App\Models\AuditLog;

class AuditTenantSettingsUpdated
{
    public function handle(TenantSettingsUpdated $event): void
    {
        AuditLog::record(
            event: 'tenant_settings_updated',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
