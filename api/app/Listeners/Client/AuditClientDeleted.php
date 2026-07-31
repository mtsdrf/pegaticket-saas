<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientDeleted;
use App\Models\AuditLog;

class AuditClientDeleted
{
    public function handle(ClientDeleted $event): void
    {
        AuditLog::record(
            event: 'client_deleted',
            model: null,
            meta: ['client_uuid' => $event->clientUuid],
            actorId: $event->actorId
        );
    }
}
