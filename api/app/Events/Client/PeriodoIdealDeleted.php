<?php

namespace App\Events\Client;

class PeriodoIdealDeleted
{
    public function __construct(
        public string $periodoIdealUuid,
        public int $actorId
    ) {
    }
}
