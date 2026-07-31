<?php

namespace App\Listeners\Location;

use App\Events\Location\BairroCreated;
use App\Models\AuditLog;

class AuditBairroCreated
{
    public function handle(BairroCreated $event): void
    {
        AuditLog::record(
            event: 'bairro_created',
            model: null,
            meta: ['bairro_uuid' => $event->bairroUuid],
            actorId: $event->actorId
        );
    }
}
