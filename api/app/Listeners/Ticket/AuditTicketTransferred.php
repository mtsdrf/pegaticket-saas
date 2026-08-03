<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketTransferred;
use App\Models\AuditLog;

class AuditTicketTransferred
{
    public function handle(TicketTransferred $event): void
    {
        AuditLog::record(
            event: 'ticket_transferred',
            model: null,
            meta: [
                'ticket_uuid' => $event->ticketUuid,
                'previous_attendee_name' => $event->previousAttendeeName,
                'new_attendee_name' => $event->newAttendeeName,
                'final_customer_uuid' => $event->finalCustomerUuid,
            ],
            actorId: null
        );
    }
}
