<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketBatchCreated;
use App\Models\AuditLog;

class AuditTicketBatchCreated
{
    public function handle(TicketBatchCreated $event): void
    {
        AuditLog::record(
            event: 'ticket_batch_created',
            model: null,
            meta: ['ticket_batch_uuid' => $event->ticketBatchUuid],
            actorId: $event->actorId
        );
    }
}
