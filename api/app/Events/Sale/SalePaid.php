<?php

namespace App\Events\Sale;

class SalePaid
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
