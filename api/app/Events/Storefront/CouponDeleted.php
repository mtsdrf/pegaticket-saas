<?php

namespace App\Events\Storefront;

class CouponDeleted
{
    public function __construct(
        public string $couponUuid,
        public int $actorId
    ) {
    }
}
