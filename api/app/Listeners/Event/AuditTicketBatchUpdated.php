<?php

namespace App\Listeners\Event;

use App\Events\Event\TicketBatchUpdated;
use App\Models\AuditLog;

class AuditTicketBatchUpdated
{
    public function handle(TicketBatchUpdated $event): void
    {
        AuditLog::record(
            event: 'ticket_batch_updated',
            model: null,
            meta: [
                'ticket_batch_uuid' => $event->ticketBatchUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
