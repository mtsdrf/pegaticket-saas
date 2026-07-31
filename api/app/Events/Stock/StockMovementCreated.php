<?php

namespace App\Events\Stock;

class StockMovementCreated
{
    public function __construct(
        public string $stockMovementUuid,
        public string $type,
        public string $ticketTypeUuid,
        public string $locationUuid,
        public float $quantity,
        public ?int $actorId
    ) {
    }
}
