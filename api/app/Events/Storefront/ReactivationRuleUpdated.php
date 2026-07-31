<?php

namespace App\Events\Storefront;

class ReactivationRuleUpdated
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $actorId,
    ) {
    }
}
