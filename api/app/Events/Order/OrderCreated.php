<?php

namespace App\Events\Order;

class OrderCreated
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public ?int $actorId
    ) {
    }
}
