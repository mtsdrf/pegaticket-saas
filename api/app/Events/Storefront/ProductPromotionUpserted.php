<?php

namespace App\Events\Storefront;

class ProductPromotionUpserted
{
    public function __construct(
        public string $productPromotionUuid,
        public int $actorId
    ) {
    }
}
