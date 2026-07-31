<?php

namespace App\Events\Venue;

class SeatUpdated
{
    public function __construct(
        public string $seatUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
