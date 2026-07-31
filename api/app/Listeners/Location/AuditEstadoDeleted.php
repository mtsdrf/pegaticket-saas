<?php

namespace App\Listeners\Location;

use App\Events\Location\EstadoDeleted;
use App\Models\AuditLog;

class AuditEstadoDeleted
{
    public function handle(EstadoDeleted $event): void
    {
        AuditLog::record(
            event: 'estado_deleted',
            model: null,
            meta: ['estado_uuid' => $event->estadoUuid],
            actorId: $event->actorId
        );
    }
}
