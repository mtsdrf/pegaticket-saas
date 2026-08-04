<?php

namespace App\Listeners\Event;

use App\Events\Event\EventGateDeleted;
use App\Models\AuditLog;

class AuditEventGateDeleted
{
    public function handle(EventGateDeleted $event): void
    {
        AuditLog::record(
            event: 'event_gate_deleted',
            model: null,
            meta: ['event_gate_uuid' => $event->eventGateUuid],
            actorId: $event->actorId
        );
    }
}
