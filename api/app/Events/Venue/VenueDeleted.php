<?php

namespace App\Events\Venue;

class VenueDeleted
{
    public function __construct(
        public string $venueUuid,
        public int $actorId
    ) {
    }
}
