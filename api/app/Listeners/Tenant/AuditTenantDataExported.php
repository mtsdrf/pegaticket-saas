<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantDataExported;
use App\Models\AuditLog;

class AuditTenantDataExported
{
    public function handle(TenantDataExported $event): void
    {
        AuditLog::record(
            event: 'tenant_data_exported',
            model: null,
            meta: [
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
