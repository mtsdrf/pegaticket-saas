<?php

namespace App\Events\Sale;

class SaleReopened
{
    public function __construct(
        public string $saleUuid,
        public int $actorId
    ) {
    }
}
