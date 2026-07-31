<?php

namespace App\Events\Location;

class EstadoDeleted
{
    public function __construct(
        public string $estadoUuid,
        public int $actorId
    ) {
    }
}
