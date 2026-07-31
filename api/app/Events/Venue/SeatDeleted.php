<?php

namespace App\Events\Venue;

class SeatDeleted
{
    public function __construct(
        public string $seatUuid,
        public int $actorId
    ) {
    }
}
