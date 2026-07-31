<?php

namespace App\Events\Order;

class OrderPartiallyPaid
{
    public function __construct(
        public string $orderUuid,
        public string $amount,
        public int $actorId
    ) {
    }
}
