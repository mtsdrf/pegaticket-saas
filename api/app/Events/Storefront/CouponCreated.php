<?php

namespace App\Events\Storefront;

class CouponCreated
{
    public function __construct(
        public string $couponUuid,
        public int $actorId
    ) {
    }
}
