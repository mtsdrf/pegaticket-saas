<?php

namespace App\Events\Balcao;

class ComandaClosed
{
    /**
     * @param array<int, array{method: string, amount: float}> $payments
     */
    public function __construct(
        public string $comandaUuid,
        public string $orderUuid,
        public float $total,
        public float $serviceFee,
        public array $payments,
        public int $actorId
    ) {
    }
}
