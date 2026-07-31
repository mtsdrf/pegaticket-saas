<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\TenantUserInviteAccepted;
use App\Models\AuditLog;

class AuditTenantUserInviteAccepted
{
    public function handle(TenantUserInviteAccepted $event): void
    {
        AuditLog::record(
            event: 'tenant_user_invite_accepted',
            model: null,
            meta: [
                'tenant_user_invite_uuid' => $event->tenantUserInviteUuid,
                'user_uuid' => $event->userUuid,
            ],
            actorId: $event->actorId
        );
    }
}
