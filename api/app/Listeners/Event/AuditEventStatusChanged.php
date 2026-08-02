<?php

namespace App\Listeners\Event;

use App\Events\Event\EventStatusChanged;
use App\Models\AuditLog;

class AuditEventStatusChanged
{
    public function handle(EventStatusChanged $event): void
    {
        AuditLog::record(
            event: 'event_status_changed',
            model: null,
            meta: [
                'event_uuid' => $event->eventUuid,
                'from_status' => $event->fromStatus,
                'to_status' => $event->toStatus,
            ],
            actorId: $event->actorId
        );
    }
}
