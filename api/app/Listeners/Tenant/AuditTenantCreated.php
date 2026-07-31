<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantCreated;
use App\Models\AuditLog;

class AuditTenantCreated
{
    public function handle(TenantCreated $event): void
    {
        AuditLog::record(
            event: 'tenant_created',
            model: null,
            meta: ['tenant_uuid' => $event->tenantUuid],
            actorId: $event->actorId
        );
    }
}