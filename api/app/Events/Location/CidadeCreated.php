<?php

namespace App\Events\Location;

class CidadeCreated
{
    public function __construct(
        public string $cidadeUuid,
        public int $actorId
    ) {
    }
}
