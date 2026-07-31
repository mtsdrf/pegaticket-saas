<?php

namespace App\Events\Storefront;

class CouponUpdated
{
    public function __construct(
        public string $couponUuid,
        public int $actorId
    ) {
    }
}
