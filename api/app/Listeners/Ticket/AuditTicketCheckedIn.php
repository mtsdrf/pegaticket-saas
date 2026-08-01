<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketCheckedIn;
use App\Models\AuditLog;

class AuditTicketCheckedIn
{
    public function handle(TicketCheckedIn $event): void
    {
        AuditLog::record(
            event: 'ticket_checked_in',
            model: null,
            meta: [
                'ticket_uuid' => $event->ticketUuid,
                'result' => $event->result,
            ],
            actorId: $event->actorId
        );
    }
}
