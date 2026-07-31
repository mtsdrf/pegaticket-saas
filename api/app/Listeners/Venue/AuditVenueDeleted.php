<?php

namespace App\Listeners\Venue;

use App\Events\Venue\VenueDeleted;
use App\Models\AuditLog;

class AuditVenueDeleted
{
    public function handle(VenueDeleted $event): void
    {
        AuditLog::record(
            event: 'venue_deleted',
            model: null,
            meta: ['venue_uuid' => $event->venueUuid],
            actorId: $event->actorId
        );
    }
}
