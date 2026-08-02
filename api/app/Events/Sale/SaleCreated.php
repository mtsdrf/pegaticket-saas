<?php

namespace App\Events\Sale;

class SaleCreated
{
    public function __construct(
        public int $saleId,
        public string $saleUuid,
        public ?int $actorId
    ) {
    }
}
