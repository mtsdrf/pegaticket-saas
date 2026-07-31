<?php

namespace App\Events\Stock;

class StockMovementCreated
{
    public function __construct(
        public string $stockMovementUuid,
        public string $type,
        public string $productUuid,
        public string $locationUuid,
        public float $quantity,
        public ?int $actorId
    ) {
    }
}
