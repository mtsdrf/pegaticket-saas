<?php

namespace App\Listeners\Location;

use App\Events\Location\EstadoUpdated;
use App\Models\AuditLog;

class AuditEstadoUpdated
{
    public function handle(EstadoUpdated $event): void
    {
        AuditLog::record(
            event: 'estado_updated',
            model: null,
            meta: [
                'estado_uuid' => $event->estadoUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
