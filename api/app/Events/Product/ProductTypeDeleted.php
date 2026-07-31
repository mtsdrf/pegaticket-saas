<?php

namespace App\Events\Product;

class ProductTypeDeleted
{
    public function __construct(
        public string $productTypeUuid,
        public int $actorId
    ) {
    }
}
