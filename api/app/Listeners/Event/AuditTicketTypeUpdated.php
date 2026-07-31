<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeUpdated;
use App\Models\AuditLog;

class AuditTicketTypeUpdated
{
    public function handle(TicketTypeUpdated $event): void
    {
        AuditLog::record(
            event: 'ticket_type_updated',
            model: null,
            meta: [
                'ticket_type_uuid' => $event->ticketTypeUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
