<?php

namespace App\Listeners\Location;

use App\Events\Location\CidadeCreated;
use App\Models\AuditLog;

class AuditCidadeCreated
{
    public function handle(CidadeCreated $event): void
    {
        AuditLog::record(
            event: 'cidade_created',
            model: null,
            meta: ['cidade_uuid' => $event->cidadeUuid],
            actorId: $event->actorId
        );
    }
}
