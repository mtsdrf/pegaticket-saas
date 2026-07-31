<?php

namespace App\Events\Location;

class EstadoUpdated
{
    public function __construct(
        public string $estadoUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
