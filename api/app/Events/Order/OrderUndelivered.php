<?php

namespace App\Events\Order;

class OrderUndelivered
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
