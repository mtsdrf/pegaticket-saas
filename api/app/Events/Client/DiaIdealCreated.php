<?php

namespace App\Events\Client;

class DiaIdealCreated
{
    public function __construct(
        public string $diaIdealUuid,
        public int $actorId
    ) {
    }
}
