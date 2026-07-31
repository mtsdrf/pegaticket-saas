<?php

namespace App\Events\Tenant;

class TenantDataExported
{
    public function __construct(
        public int $tenantId,
        public int $actorId
    ) {
    }
}
