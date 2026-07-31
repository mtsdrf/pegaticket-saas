<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantDeleted;
use App\Models\AuditLog;

class AuditTenantDeleted
{
    public function handle(TenantDeleted $event): void
    {
        AuditLog::record(
            event: 'tenant_deleted',
            model: null,
            meta: ['tenant_uuid' => $event->tenantUuid],
            actorId: $event->actorId
        );
    }
}