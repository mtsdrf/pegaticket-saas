<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantRolePermissionsSynced;
use App\Models\AuditLog;

class AuditTenantRolePermissionsSynced
{
    public function handle(TenantRolePermissionsSynced $event): void
    {
        AuditLog::record(
            event: 'tenant_role_permissions_synced',
            model: null,
            meta: ['tenant_role_uuid' => $event->tenantRoleUuid],
            actorId: $event->actorId
        );
    }
}