<?php

namespace App\Events\Location;

class BairroCreated
{
    public function __construct(
        public string $bairroUuid,
        public int $actorId
    ) {
    }
}
