<?php

namespace App\Listeners\Location;

use App\Events\Location\BairroUpdated;
use App\Models\AuditLog;

class AuditBairroUpdated
{
    public function handle(BairroUpdated $event): void
    {
        AuditLog::record(
            event: 'bairro_updated',
            model: null,
            meta: [
                'bairro_uuid' => $event->bairroUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
