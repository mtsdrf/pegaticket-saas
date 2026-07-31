<?php

namespace App\Events\Stock;

class StockLocationDeleted
{
    public function __construct(
        public string $stockLocationUuid,
        public int $actorId
    ) {
    }
}
