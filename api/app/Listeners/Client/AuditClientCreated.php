<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientCreated;
use App\Models\AuditLog;

class AuditClientCreated
{
    public function handle(ClientCreated $event): void
    {
        AuditLog::record(
            event: 'client_created',
            model: null,
            meta: ['client_uuid' => $event->clientUuid],
            actorId: $event->actorId
        );
    }
}
