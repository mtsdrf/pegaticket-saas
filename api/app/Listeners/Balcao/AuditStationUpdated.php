<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\StationUpdated;
use App\Models\AuditLog;

class AuditStationUpdated
{
    public function handle(StationUpdated $event): void
    {
        AuditLog::record(
            event: 'station_updated',
            model: null,
            meta: ['station_uuid' => $event->stationUuid, 'changes' => $event->changes],
            actorId: $event->actorId
        );
    }
}
