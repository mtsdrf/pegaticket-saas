<?php

namespace App\Events\Sale;

class SalePaid
{
    public function __construct(
        public string $saleUuid,
        public int $actorId
    ) {
    }
}
