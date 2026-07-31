<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUserInvited;
use App\Models\AuditLog;

class AuditTenantUserInvited
{
    public function handle(TenantUserInvited $event): void
    {
        AuditLog::record(
            event: 'tenant_user_invited',
            model: null,
            meta: ['tenant_user_invite_uuid' => $event->tenantUserInviteUuid],
            actorId: $event->actorId
        );
    }
}
