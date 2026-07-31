<?php

namespace App\Events\Order;

class OrderUnpaid
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
