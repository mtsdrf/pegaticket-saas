<?php

namespace App\Events\Order;

class OrderPaid
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
