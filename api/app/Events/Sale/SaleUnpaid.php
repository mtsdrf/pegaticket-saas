<?php

namespace App\Events\Sale;

class SaleUnpaid
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
