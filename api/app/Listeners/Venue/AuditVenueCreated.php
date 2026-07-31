<?php

namespace App\Listeners\Venue;

use App\Events\Venue\VenueCreated;
use App\Models\AuditLog;

class AuditVenueCreated
{
    public function handle(VenueCreated $event): void
    {
        AuditLog::record(
            event: 'venue_created',
            model: null,
            meta: ['venue_uuid' => $event->venueUuid],
            actorId: $event->actorId
        );
    }
}
