<?php

namespace App\Events\Tenant;

class TenantCreated
{
    public function __construct(
        public string $tenantUuid,
        public int $actorId
    ) {
    }
}