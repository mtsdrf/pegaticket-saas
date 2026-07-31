<?php

namespace App\Events\Venue;

class VenueCreated
{
    public function __construct(
        public string $venueUuid,
        public int $actorId
    ) {
    }
}
