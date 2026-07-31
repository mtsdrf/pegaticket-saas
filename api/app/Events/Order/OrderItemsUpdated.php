<?php

namespace App\Events\Order;

class OrderItemsUpdated
{
    public function __construct(
        public string $orderUuid,
        public int $actorId
    ) {
    }
}
