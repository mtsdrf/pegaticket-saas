<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\StationCreated;
use App\Models\AuditLog;

class AuditStationCreated
{
    public function handle(StationCreated $event): void
    {
        AuditLog::record(
            event: 'station_created',
            model: null,
            meta: ['station_uuid' => $event->stationUuid],
            actorId: $event->actorId
        );
    }
}
