<?php

namespace App\Events\Sale;

class SaleCompleted
{
    public function __construct(
        public int $saleId,
        public string $saleUuid,
        public string $fromStage,
        public string $toStage,
        public int $actorId
    ) {
    }
}
