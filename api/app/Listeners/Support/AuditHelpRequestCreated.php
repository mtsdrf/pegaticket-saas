<?php

namespace App\Listeners\Support;

use App\Events\Support\HelpRequestCreated;
use App\Models\AuditLog;

class AuditHelpRequestCreated
{
    public function handle(HelpRequestCreated $event): void
    {
        AuditLog::record(
            event: 'help_request_created',
            model: null,
            meta: [
                'ticket_uuid' => $event->ticketUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
