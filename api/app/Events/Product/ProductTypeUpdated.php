<?php

namespace App\Events\Product;

class ProductTypeUpdated
{
    public function __construct(
        public string $productTypeUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
