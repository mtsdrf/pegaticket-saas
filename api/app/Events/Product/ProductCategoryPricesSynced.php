<?php

namespace App\Events\Product;

class ProductCategoryPricesSynced
{
    public function __construct(
        public string $productUuid,
        public array $categoryPrices,
        public int $actorId
    ) {
    }
}
