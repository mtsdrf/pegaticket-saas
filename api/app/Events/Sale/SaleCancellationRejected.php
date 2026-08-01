<?php

namespace App\Events\Sale;

class SaleCancellationRejected
{
    public function __construct(
        public string $orderUuid,
        public ?int $actorId,
    ) {
    }
}
