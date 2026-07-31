<?php

namespace App\Events\Stock;

class StockLocationUpdated
{
    public function __construct(
        public string $stockLocationUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
