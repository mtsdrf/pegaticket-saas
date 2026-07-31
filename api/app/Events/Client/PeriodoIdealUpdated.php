<?php

namespace App\Events\Client;

class PeriodoIdealUpdated
{
    public function __construct(
        public string $periodoIdealUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
