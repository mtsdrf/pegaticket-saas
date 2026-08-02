<?php

namespace App\Events\Sale;

class SaleCancelled
{
    public function __construct(
        public int $saleId,
        public string $saleUuid,
        public string $fromStage,
        public string $toStage,
        public string $cancellationReason,
        public int $actorId
    ) {
    }
}
