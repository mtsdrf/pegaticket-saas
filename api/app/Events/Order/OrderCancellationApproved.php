<?php

namespace App\Events\Order;

class OrderCancellationApproved
{
    public function __construct(
        public string $orderUuid,
        public ?int $actorId,
    ) {
    }
}
