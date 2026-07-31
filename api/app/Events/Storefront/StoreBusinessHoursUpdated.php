<?php

namespace App\Events\Storefront;

class StoreBusinessHoursUpdated
{
    public function __construct(
        public int $tenantId,
        public int $actorId
    ) {
    }
}
