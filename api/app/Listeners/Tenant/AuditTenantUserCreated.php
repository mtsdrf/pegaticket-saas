<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUserCreated;
use App\Models\AuditLog;

class AuditTenantUserCreated
{
    public function handle(TenantUserCreated $event): void
    {
        AuditLog::record(
            event: 'tenant_user_created',
            model: null,
            meta: ['tenant_user_uuid' => $event->tenantUserUuid],
            actorId: $event->actorId
        );
    }
}