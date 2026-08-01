<?php

namespace App\Events\Sale;

class SaleUndispatched
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public string $fromStage,
        public string $toStage,
        public ?int $actorId
    ) {
    }
}
