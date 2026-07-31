<?php

namespace App\Listeners\Event;

use App\Events\Event\EventUpdated;
use App\Models\AuditLog;

class AuditEventUpdated
{
    public function handle(EventUpdated $event): void
    {
        AuditLog::record(
            event: 'event_updated',
            model: null,
            meta: [
                'event_uuid' => $event->eventUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
