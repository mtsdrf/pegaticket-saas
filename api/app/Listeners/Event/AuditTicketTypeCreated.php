<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeCreated;
use App\Models\AuditLog;

class AuditTicketTypeCreated
{
    public function handle(TicketTypeCreated $event): void
    {
        AuditLog::record(
            event: 'ticket_type_created',
            model: null,
            meta: ['ticket_type_uuid' => $event->ticketTypeUuid],
            actorId: $event->actorId
        );
    }
}
