<?php

namespace App\Listeners\Venue;

use App\Events\Venue\VenueUpdated;
use App\Models\AuditLog;

class AuditVenueUpdated
{
    public function handle(VenueUpdated $event): void
    {
        AuditLog::record(
            event: 'venue_updated',
            model: null,
            meta: [
                'venue_uuid' => $event->venueUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
