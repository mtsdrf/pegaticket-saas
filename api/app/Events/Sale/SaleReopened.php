<?php

namespace App\Events\Sale;

class SaleReopened
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
