<?php

namespace App\Events\Sale;

class SaleItemsUpdated
{
    public function __construct(
        public string $saleUuid,
        public int $actorId
    ) {
    }
}
