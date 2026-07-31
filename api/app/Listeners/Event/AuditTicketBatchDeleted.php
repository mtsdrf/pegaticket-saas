<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketBatchDeleted;
use App\Models\AuditLog;

class AuditTicketBatchDeleted
{
    public function handle(TicketBatchDeleted $event): void
    {
        AuditLog::record(
            event: 'ticket_batch_deleted',
            model: null,
            meta: ['ticket_batch_uuid' => $event->ticketBatchUuid],
            actorId: $event->actorId
        );
    }
}
