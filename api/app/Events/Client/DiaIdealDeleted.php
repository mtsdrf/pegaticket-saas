<?php

namespace App\Events\Client;

class DiaIdealDeleted
{
    public function __construct(
        public string $diaIdealUuid,
        public int $actorId
    ) {
    }
}
