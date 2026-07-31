<?php

namespace App\Events\Product;

class ProductUpdated
{
    public function __construct(
        public string $productUuid,
        public ?int $actorId,
        public array $changes
    ) {
    }
}
