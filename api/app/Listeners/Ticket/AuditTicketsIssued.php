<?php

namespace App\Listeners\Ticket;

use App\Events\Ticket\TicketsIssued;
use App\Models\AuditLog;

class AuditTicketsIssued
{
    public function handle(TicketsIssued $event): void
    {
        AuditLog::record(
            event: 'tickets_issued',
            model: null,
            meta: [
                'sale_uuid' => $event->saleUuid,
                'ticket_uuids' => $event->ticketUuids,
            ],
            actorId: $event->actorId
        );
    }
}
