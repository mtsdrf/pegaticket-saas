<?php

namespace App\Events\Tenant;

class TenantRolePermissionsSynced
{
    public function __construct(
        public string $tenantRoleUuid,
        public int $actorId
    ) {
    }
}