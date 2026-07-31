<?php

namespace App\Listeners\Event;

use App\Events\Event\EventCategoryUpdated;
use App\Models\AuditLog;

class AuditEventCategoryUpdated
{
    public function handle(EventCategoryUpdated $event): void
    {
        AuditLog::record(
            event: 'event_category_updated',
            model: null,
            meta: [
                'event_category_uuid' => $event->eventCategoryUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
