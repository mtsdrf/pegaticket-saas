<?php

namespace App\Listeners\Event;

use App\Events\Event\EventDeleted;
use App\Models\AuditLog;

class AuditEventDeleted
{
    public function handle(EventDeleted $event): void
    {
        AuditLog::record(
            event: 'event_deleted',
            model: null,
            meta: ['event_uuid' => $event->eventUuid],
            actorId: $event->actorId
        );
    }
}
