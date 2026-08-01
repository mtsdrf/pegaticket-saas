<?php

namespace App\Events\Sale;

class SaleCancelled
{
    public function __construct(
        public int $orderId,
        public string $orderUuid,
        public string $fromStage,
        public string $toStage,
        public string $cancellationReason,
        public int $actorId
    ) {
    }
}
