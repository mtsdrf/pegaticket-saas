<?php

namespace App\Listeners\Event;

use App\Events\Event\EventSessionDeleted;
use App\Models\AuditLog;

class AuditEventSessionDeleted
{
    public function handle(EventSessionDeleted $event): void
    {
        AuditLog::record(
            event: 'event_session_deleted',
            model: null,
            meta: ['event_session_uuid' => $event->eventSessionUuid],
            actorId: $event->actorId
        );
    }
}
