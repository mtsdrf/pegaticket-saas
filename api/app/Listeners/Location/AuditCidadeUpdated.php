<?php

namespace App\Listeners\Location;

use App\Events\Location\CidadeUpdated;
use App\Models\AuditLog;

class AuditCidadeUpdated
{
    public function handle(CidadeUpdated $event): void
    {
        AuditLog::record(
            event: 'cidade_updated',
            model: null,
            meta: [
                'cidade_uuid' => $event->cidadeUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
