<?php

namespace App\Events\Pdv;

class PdvSaleCompleted
{
    /**
     * @param array<int, array{method: string, amount: float}> $payments
     */
    public function __construct(
        public string $orderUuid,
        public string $cashSessionUuid,
        public array $payments,
        public int $actorId
    ) {
    }
}
