<?php

namespace App\Events\Pdv;

class CashSessionClosed
{
    public function __construct(
        public string $cashSessionUuid,
        public float $difference,
        public int $actorId
    ) {
    }
}
