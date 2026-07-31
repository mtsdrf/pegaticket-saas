<?php

namespace App\Listeners\Venue;

use App\Events\Venue\SeatCreated;
use App\Models\AuditLog;

class AuditSeatCreated
{
    public function handle(SeatCreated $event): void
    {
        AuditLog::record(
            event: 'seat_created',
            model: null,
            meta: ['seat_uuid' => $event->seatUuid],
            actorId: $event->actorId
        );
    }
}
