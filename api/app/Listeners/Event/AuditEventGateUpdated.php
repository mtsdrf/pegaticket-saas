<?php

namespace App\Listeners\Event;

use App\Events\Event\EventGateUpdated;
use App\Models\AuditLog;

class AuditEventGateUpdated
{
    public function handle(EventGateUpdated $event): void
    {
        AuditLog::record(
            event: 'event_gate_updated',
            model: null,
            meta: [
                'event_gate_uuid' => $event->eventGateUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
