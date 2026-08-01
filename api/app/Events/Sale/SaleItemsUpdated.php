<?php

namespace App\Events\Sale;

class SaleItemsUpdated
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
