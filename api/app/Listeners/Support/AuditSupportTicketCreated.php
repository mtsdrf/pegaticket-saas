<?php

namespace App\Listeners\Support;

use App\Events\Support\SupportTicketCreated;
use App\Models\AuditLog;

class AuditSupportTicketCreated
{
    public function handle(SupportTicketCreated $event): void
    {
        AuditLog::record(
            event: 'support_ticket_created',
            model: null,
            meta: [
                'ticket_uuid' => $event->ticketUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
