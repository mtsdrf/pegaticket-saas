<?php

namespace App\Events\Balcao;

class StationDeleted
{
    public function __construct(
        public string $stationUuid,
        public int $actorId
    ) {
    }
}
