<?php

namespace App\Events\Sale;

class SaleUndelivered
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
