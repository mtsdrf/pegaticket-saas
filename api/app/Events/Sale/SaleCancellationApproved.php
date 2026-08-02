<?php

namespace App\Events\Sale;

class SaleCancellationApproved
{
    public function __construct(
        public string $saleUuid,
        public ?int $actorId,
    ) {
    }
}
