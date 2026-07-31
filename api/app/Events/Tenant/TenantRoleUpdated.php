<?php

namespace App\Events\Tenant;

class TenantRoleUpdated
{
    public function __construct(
        public string $tenantRoleUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}