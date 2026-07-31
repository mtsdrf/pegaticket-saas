<?php

namespace App\Listeners\Location;

use App\Events\Location\EstadoCreated;
use App\Models\AuditLog;

class AuditEstadoCreated
{
    public function handle(EstadoCreated $event): void
    {
        AuditLog::record(
            event: 'estado_created',
            model: null,
            meta: ['estado_uuid' => $event->estadoUuid],
            actorId: $event->actorId
        );
    }
}
