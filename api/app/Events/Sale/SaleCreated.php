<?php

namespace App\Events\Sale;

class SaleCreated
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public ?int $actorId
    ) {
    }
}
