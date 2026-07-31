<?php

namespace App\Events\Order;

class OrderRejected
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public string $fromStage,
        public string $toStage,
        public ?string $reason,
        public ?int $actorId
    ) {
    }
}
