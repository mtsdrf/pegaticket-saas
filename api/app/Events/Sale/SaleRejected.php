<?php

namespace App\Events\Sale;

class SaleRejected
{
    public function __construct(
        public int $saleId,
        public string $saleUuid,
        public string $fromStage,
        public string $toStage,
        public ?string $reason,
        public ?int $actorId
    ) {
    }
}
