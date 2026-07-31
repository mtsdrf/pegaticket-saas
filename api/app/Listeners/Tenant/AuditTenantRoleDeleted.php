<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantRoleDeleted;
use App\Models\AuditLog;

class AuditTenantRoleDeleted
{
    public function handle(TenantRoleDeleted $event): void
    {
        AuditLog::record(
            event: 'tenant_role_deleted',
            model: null,
            meta: ['tenant_role_uuid' => $event->tenantRoleUuid],
            actorId: $event->actorId
        );
    }
}