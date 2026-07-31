<?php

namespace App\Events\Pdv;

class CashMovementRegistered
{
    public function __construct(
        public string $cashSessionUuid,
        public string $cashMovementUuid,
        public string $type,
        public float $amount,
        public int $actorId
    ) {
    }
}
