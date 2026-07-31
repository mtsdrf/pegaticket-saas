<?php

namespace App\Events\Tenant;

class TenantUserDeleted
{
    public function __construct(
        public string $tenantUserUuid,
        public int $actorId
    ) {
    }
}