<?php

namespace App\Events\Product;

class ProductCategoryDeleted
{
    public function __construct(
        public string $productCategoryUuid,
        public int $actorId
    ) {
    }
}
