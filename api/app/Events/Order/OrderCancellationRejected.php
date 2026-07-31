<?php

namespace App\Events\Order;

class OrderCancellationRejected
{
    public function __construct(
        public string $orderUuid,
        public ?int $actorId,
    ) {
    }
}
