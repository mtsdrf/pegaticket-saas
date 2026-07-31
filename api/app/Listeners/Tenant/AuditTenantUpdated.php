<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUpdated;
use App\Models\AuditLog;

class AuditTenantUpdated
{
    public function handle(TenantUpdated $event): void
    {
        AuditLog::record(
            event: 'tenant_updated',
            model: null,
            meta: [
                'tenant_uuid' => $event->tenantUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}