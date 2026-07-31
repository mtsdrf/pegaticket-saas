<?php

namespace App\Events\Storefront;

class ProductPromotionDeleted
{
    public function __construct(
        public string $productPromotionUuid,
        public int $actorId
    ) {
    }
}
