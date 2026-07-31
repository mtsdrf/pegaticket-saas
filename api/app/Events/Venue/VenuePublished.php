<?php

namespace App\Events\Venue;

class VenuePublished
{
    public function __construct(
        public string $venueUuid,
        public string $venueMapVersionUuid,
        public int $actorId
    ) {
    }
}
