<?php

namespace App\Listeners\Event;

use App\Events\Event\EventProductDeleted;
use App\Models\AuditLog;

class AuditEventProductDeleted
{
    public function handle(EventProductDeleted $event): void
    {
        AuditLog::record(
            event: 'event_product_deleted',
            model: null,
            meta: ['event_product_uuid' => $event->eventProductUuid],
            actorId: $event->actorId
        );
    }
}
