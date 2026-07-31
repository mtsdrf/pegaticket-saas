<?php

namespace App\Events\Product;

class ProductCategoryUpdated
{
    public function __construct(
        public string $productCategoryUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
