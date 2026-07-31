<?php

namespace App\Events\Tenant;

class TenantUpdated
{
    public function __construct(
        public string $tenantUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}