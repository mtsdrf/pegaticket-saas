<?php

namespace App\Events\Pdv;

class CashSessionOpened
{
    public function __construct(
        public string $cashSessionUuid,
        public int $actorId
    ) {
    }
}
