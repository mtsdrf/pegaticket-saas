<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUserUpdated;
use App\Models\AuditLog;

class AuditTenantUserUpdated
{
    public function handle(TenantUserUpdated $event): void
    {
        AuditLog::record(
            event: 'tenant_user_updated',
            model: null,
            meta: [
                'tenant_user_uuid' => $event->tenantUserUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}