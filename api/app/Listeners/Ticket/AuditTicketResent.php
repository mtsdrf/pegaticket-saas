<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketResent;
use App\Models\AuditLog;

class AuditTicketResent
{
    public function handle(TicketResent $event): void
    {
        AuditLog::record(
            event: 'ticket_resent',
            model: null,
            meta: ['ticket_uuid' => $event->ticketUuid],
            actorId: $event->actorId
        );
    }
}
