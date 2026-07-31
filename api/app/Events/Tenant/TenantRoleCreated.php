<?php

namespace App\Events\Tenant;

class TenantRoleCreated
{
    public function __construct(
        public string $tenantRoleUuid,
        public int $actorId
    ) {
    }
}