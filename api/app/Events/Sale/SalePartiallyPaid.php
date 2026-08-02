<?php

namespace App\Events\Sale;

class SalePartiallyPaid
{
    public function __construct(
        public string $saleUuid,
        public string $amount,
        public int $actorId
    ) {
    }
}
