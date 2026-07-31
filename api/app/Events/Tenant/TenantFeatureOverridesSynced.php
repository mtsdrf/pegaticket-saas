<?php

namespace App\Events\Tenant;

class TenantFeatureOverridesSynced
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $actorId,
    ) {
    }
}
