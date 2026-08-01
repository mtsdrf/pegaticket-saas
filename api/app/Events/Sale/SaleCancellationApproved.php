<?php

namespace App\Events\Sale;

class SaleCancellationApproved
{
    public function __construct(
        public string $orderUuid,
        public ?int $actorId,
    ) {
    }
}
