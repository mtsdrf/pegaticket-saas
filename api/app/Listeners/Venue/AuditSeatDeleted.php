<?php

namespace App\Listeners\Venue;

use App\Events\Venue\SeatDeleted;
use App\Models\AuditLog;

class AuditSeatDeleted
{
    public function handle(SeatDeleted $event): void
    {
        AuditLog::record(
            event: 'seat_deleted',
            model: null,
            meta: ['seat_uuid' => $event->seatUuid],
            actorId: $event->actorId
        );
    }
}
