<?php

namespace App\Listeners\Event;

use App\Events\Event\EventProductCreated;
use App\Models\AuditLog;

class AuditEventProductCreated
{
    public function handle(EventProductCreated $event): void
    {
        AuditLog::record(
            event: 'event_product_created',
            model: null,
            meta: ['event_product_uuid' => $event->eventProductUuid],
            actorId: $event->actorId
        );
    }
}
