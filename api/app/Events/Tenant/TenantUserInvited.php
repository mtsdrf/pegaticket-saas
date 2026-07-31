<?php

namespace App\Events\Tenant;

class TenantUserInvited
{
    public function __construct(
        public string $tenantUserInviteUuid,
        public int $actorId
    ) {
    }
}
