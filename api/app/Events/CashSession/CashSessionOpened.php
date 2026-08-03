<?php

namespace App\Events\CashSession;

class CashSessionOpened
{
    public function __construct(
        public string $cashSessionUuid,
        public float $openingAmount,
        public int $actorId
    ) {}
}
