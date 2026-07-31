<?php

namespace App\Events\Tenant;

class TenantUserCreated
{
    public function __construct(
        public string $tenantUserUuid,
        public int $actorId
    ) {
    }
}