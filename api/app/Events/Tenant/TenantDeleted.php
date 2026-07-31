<?php

namespace App\Events\Tenant;

class TenantDeleted
{
    public function __construct(
        public string $tenantUuid,
        public int $actorId
    ) {
    }
}