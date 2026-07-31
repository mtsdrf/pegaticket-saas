<?php

namespace App\Listeners\Event;

use App\Events\Event\EventSessionCreated;
use App\Models\AuditLog;

class AuditEventSessionCreated
{
    public function handle(EventSessionCreated $event): void
    {
        AuditLog::record(
            event: 'event_session_created',
            model: null,
            meta: ['event_session_uuid' => $event->eventSessionUuid],
            actorId: $event->actorId
        );
    }
}
