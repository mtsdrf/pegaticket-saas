<?php

namespace App\Events\Product;

class ProductTypeCreated
{
    public function __construct(
        public string $productTypeUuid,
        public int $actorId
    ) {
    }
}
