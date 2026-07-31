<?php

namespace App\Events\Location;

class CidadeUpdated
{
    public function __construct(
        public string $cidadeUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
