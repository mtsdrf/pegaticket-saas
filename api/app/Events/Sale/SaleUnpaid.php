<?php

namespace App\Events\Sale;

class SaleUnpaid
{
    public function __construct(
        public string $saleUuid,
        public int $actorId
    ) {
    }
}
