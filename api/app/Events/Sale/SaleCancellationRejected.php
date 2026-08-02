<?php

namespace App\Events\Sale;

class SaleCancellationRejected
{
    public function __construct(
        public string $saleUuid,
        public ?int $actorId,
    ) {
    }
}
