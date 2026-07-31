<?php

namespace App\Events\Location;

class BairroDeleted
{
    public function __construct(
        public string $bairroUuid,
        public int $actorId
    ) {
    }
}
