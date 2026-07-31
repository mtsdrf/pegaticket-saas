<?php

namespace App\Events\Client;

class PeriodoIdealCreated
{
    public function __construct(
        public string $periodoIdealUuid,
        public int $actorId
    ) {
    }
}
