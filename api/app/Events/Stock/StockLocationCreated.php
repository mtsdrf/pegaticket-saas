<?php

namespace App\Events\Stock;

class StockLocationCreated
{
    public function __construct(
        public string $stockLocationUuid,
        public int $actorId
    ) {
    }
}
