<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantRoleUpdated;
use App\Models\AuditLog;

class AuditTenantRoleUpdated
{
    public function handle(TenantRoleUpdated $event): void
    {
        AuditLog::record(
            event: 'tenant_role_updated',
            model: null,
            meta: [
                'tenant_role_uuid' => $event->tenantRoleUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}