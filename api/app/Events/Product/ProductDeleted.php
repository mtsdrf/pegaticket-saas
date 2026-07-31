<?php

namespace App\Events\Product;

class ProductDeleted
{
    public function __construct(
        public string $productUuid,
        public int $actorId
    ) {
    }
}
