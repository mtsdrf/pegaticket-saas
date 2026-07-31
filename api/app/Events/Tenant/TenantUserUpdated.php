<?php

namespace App\Events\Tenant;

class TenantUserUpdated
{
    public function __construct(
        public string $tenantUserUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}