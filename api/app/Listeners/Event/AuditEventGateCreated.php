<?php

namespace App\Listeners\Event;

use App\Events\Event\EventGateCreated;
use App\Models\AuditLog;

class AuditEventGateCreated
{
    public function handle(EventGateCreated $event): void
    {
        AuditLog::record(
            event: 'event_gate_created',
            model: null,
            meta: ['event_gate_uuid' => $event->eventGateUuid],
            actorId: $event->actorId
        );
    }
}
