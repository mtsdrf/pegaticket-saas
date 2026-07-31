<?php

namespace App\Listeners\Event;

use App\Events\Event\EventProductUpdated;
use App\Models\AuditLog;

class AuditEventProductUpdated
{
    public function handle(EventProductUpdated $event): void
    {
        AuditLog::record(
            event: 'event_product_updated',
            model: null,
            meta: [
                'event_product_uuid' => $event->eventProductUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
