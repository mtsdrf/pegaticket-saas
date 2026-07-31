<?php

namespace App\Listeners\Venue;

use App\Events\Venue\VenuePublished;
use App\Models\AuditLog;

class AuditVenuePublished
{
    public function handle(VenuePublished $event): void
    {
        AuditLog::record(
            event: 'venue_published',
            model: null,
            meta: [
                'venue_uuid' => $event->venueUuid,
                'venue_map_version_uuid' => $event->venueMapVersionUuid,
            ],
            actorId: $event->actorId
        );
    }
}
