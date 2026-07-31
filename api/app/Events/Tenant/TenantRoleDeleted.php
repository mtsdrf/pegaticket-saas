<?php

namespace App\Events\Tenant;

class TenantRoleDeleted
{
    public function __construct(
        public string $tenantRoleUuid,
        public int $actorId
    ) {
    }
}