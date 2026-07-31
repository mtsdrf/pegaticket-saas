<?php

namespace App\Events\Venue;

class VenueUpdated
{
    public function __construct(
        public string $venueUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
