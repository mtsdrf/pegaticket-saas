<?php

namespace App\Events\Location;

class CidadeDeleted
{
    public function __construct(
        public string $cidadeUuid,
        public int $actorId
    ) {
    }
}
