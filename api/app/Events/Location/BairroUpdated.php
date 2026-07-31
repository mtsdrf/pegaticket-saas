<?php

namespace App\Events\Location;

class BairroUpdated
{
    public function __construct(
        public string $bairroUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
