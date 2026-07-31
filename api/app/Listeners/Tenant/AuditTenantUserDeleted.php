<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUserDeleted;
use App\Models\AuditLog;

class AuditTenantUserDeleted
{
    public function handle(TenantUserDeleted $event): void
    {
        AuditLog::record(
            event: 'tenant_user_deleted',
            model: null,
            meta: ['tenant_user_uuid' => $event->tenantUserUuid],
            actorId: $event->actorId
        );
    }
}