<?php

namespace App\Events\TenantSettings;

class TenantSettingsUpdated
{
    public function __construct(
        public int $tenantId,
        public int $actorId,
        public array $changes
    ) {
    }
}
