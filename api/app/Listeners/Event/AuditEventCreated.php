<?php

namespace App\Listeners\Event;

use App\Events\Event\EventCreated;
use App\Models\AuditLog;

class AuditEventCreated
{
    public function handle(EventCreated $event): void
    {
        AuditLog::record(
            event: 'event_created',
            model: null,
            meta: ['event_uuid' => $event->eventUuid],
            actorId: $event->actorId
        );
    }
}
