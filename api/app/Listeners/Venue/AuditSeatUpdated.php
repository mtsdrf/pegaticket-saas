<?php

namespace App\Listeners\Venue;

use App\Events\Venue\SeatUpdated;
use App\Models\AuditLog;

class AuditSeatUpdated
{
    public function handle(SeatUpdated $event): void
    {
        AuditLog::record(
            event: 'seat_updated',
            model: null,
            meta: [
                'seat_uuid' => $event->seatUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
