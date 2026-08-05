<?php

namespace App\Listeners\TicketTypeWaitlist;

use App\Events\TicketTypeWaitlist\TicketTypeWaitlistEntryCreated;
use App\Models\AuditLog;

class AuditTicketTypeWaitlistEntryCreated
{
    public function handle(TicketTypeWaitlistEntryCreated $event): void
    {
        AuditLog::record(
            event: 'ticket_type_waitlist_entry_created',
            model: null,
            meta: [
                'ticket_type_waitlist_entry_uuid' => $event->ticketTypeWaitlistEntryUuid,
                'ticket_type_uuid' => $event->ticketTypeUuid,
            ],
            actorId: null
        );
    }
}
