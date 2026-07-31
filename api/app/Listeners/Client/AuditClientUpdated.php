<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientUpdated;
use App\Models\AuditLog;

class AuditClientUpdated
{
    public function handle(ClientUpdated $event): void
    {
        AuditLog::record(
            event: 'client_updated',
            model: null,
            meta: [
                'client_uuid' => $event->clientUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
