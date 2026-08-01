<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketsCancelled;
use App\Models\AuditLog;

class AuditTicketsCancelled
{
    public function handle(TicketsCancelled $event): void
    {
        AuditLog::record(
            event: 'tickets_cancelled',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'ticket_uuids' => $event->ticketUuids,
                'status' => $event->status,
            ],
            actorId: $event->actorId
        );
    }
}
