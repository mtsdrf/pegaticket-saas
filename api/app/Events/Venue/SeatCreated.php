<?php

namespace App\Events\Venue;

class SeatCreated
{
    public function __construct(
        public string $seatUuid,
        public int $actorId
    ) {
    }
}
