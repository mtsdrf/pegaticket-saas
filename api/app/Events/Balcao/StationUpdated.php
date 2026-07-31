<?php

namespace App\Events\Balcao;

class StationUpdated
{
    /**
     * @param array<int, string> $changes
     */
    public function __construct(
        public string $stationUuid,
        public int $actorId,
        public array $changes = []
    ) {
    }
}
