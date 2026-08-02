<?php

namespace App\Events\Sale;

class SaleCompleted
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public string $fromStage,
        public string $toStage,
        public int $actorId
    ) {
    }
}
