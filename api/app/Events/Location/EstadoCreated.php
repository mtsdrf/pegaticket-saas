<?php

namespace App\Events\Location;

class EstadoCreated
{
    public function __construct(
        public string $estadoUuid,
        public int $actorId
    ) {
    }
}
