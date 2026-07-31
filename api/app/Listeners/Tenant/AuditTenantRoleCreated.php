<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantRoleCreated;
use App\Models\AuditLog;

class AuditTenantRoleCreated
{
    public function handle(TenantRoleCreated $event): void
    {
        AuditLog::record(
            event: 'tenant_role_created',
            model: null,
            meta: ['tenant_role_uuid' => $event->tenantRoleUuid],
            actorId: $event->actorId
        );
    }
}