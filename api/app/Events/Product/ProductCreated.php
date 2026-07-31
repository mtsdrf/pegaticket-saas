<?php

namespace App\Events\Product;

class ProductCreated
{
    public function __construct(
        public string $productUuid,
        public int $actorId
    ) {
    }
}
