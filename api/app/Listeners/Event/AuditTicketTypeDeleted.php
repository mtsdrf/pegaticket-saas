<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketTypeDeleted;
use App\Models\AuditLog;

class AuditTicketTypeDeleted
{
    public function handle(TicketTypeDeleted $event): void
    {
        AuditLog::record(
            event: 'ticket_type_deleted',
            model: null,
            meta: ['ticket_type_uuid' => $event->ticketTypeUuid],
            actorId: $event->actorId
        );
    }
}
