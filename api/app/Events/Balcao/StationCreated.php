<?php

namespace App\Events\Balcao;

class StationCreated
{
    public function __construct(
        public string $stationUuid,
        public int $actorId
    ) {
    }
}
