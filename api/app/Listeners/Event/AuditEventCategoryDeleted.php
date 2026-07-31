<?php

namespace App\Listeners\Event;

use App\Events\Event\EventCategoryDeleted;
use App\Models\AuditLog;

class AuditEventCategoryDeleted
{
    public function handle(EventCategoryDeleted $event): void
    {
        AuditLog::record(
            event: 'event_category_deleted',
            model: null,
            meta: ['event_category_uuid' => $event->eventCategoryUuid],
            actorId: $event->actorId
        );
    }
}
