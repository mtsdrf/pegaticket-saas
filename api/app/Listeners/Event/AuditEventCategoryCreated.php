<?php

namespace App\Listeners\Event;

use App\Events\Event\EventCategoryCreated;
use App\Models\AuditLog;

class AuditEventCategoryCreated
{
    public function handle(EventCategoryCreated $event): void
    {
        AuditLog::record(
            event: 'event_category_created',
            model: null,
            meta: ['event_category_uuid' => $event->eventCategoryUuid],
            actorId: $event->actorId
        );
    }
}
