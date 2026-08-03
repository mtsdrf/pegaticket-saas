<?php

namespace App\Events\CashSession;

class CashSessionClosed
{
    public function __construct(
        public string $cashSessionUuid,
        public float $closingAmount,
        public float $expectedCashAmount,
        public float $differenceAmount,
        public int $actorId
    ) {}
}
