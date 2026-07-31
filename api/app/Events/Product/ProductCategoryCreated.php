<?php

namespace App\Events\Product;

class ProductCategoryCreated
{
    public function __construct(
        public string $productCategoryUuid,
        public int $actorId
    ) {
    }
}
