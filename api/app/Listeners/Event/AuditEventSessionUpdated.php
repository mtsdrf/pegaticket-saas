<?php

namespace App\Listeners\Event;

use App\Events\Event\EventSessionUpdated;
use App\Models\AuditLog;

class AuditEventSessionUpdated
{
    public function handle(EventSessionUpdated $event): void
    {
        AuditLog::record(
            event: 'event_session_updated',
            model: null,
            meta: [
                'event_session_uuid' => $event->eventSessionUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
