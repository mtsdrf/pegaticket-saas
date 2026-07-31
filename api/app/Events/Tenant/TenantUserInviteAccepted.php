<?php

namespace App\Events\Tenant;

class TenantUserInviteAccepted
{
    public function __construct(
        public string $tenantUserInviteUuid,
        public string $userUuid,
        public int $actorId
    ) {
    }
}
