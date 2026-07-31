<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\StationDeleted;
use App\Models\AuditLog;

class AuditStationDeleted
{
    public function handle(StationDeleted $event): void
    {
        AuditLog::record(
            event: 'station_deleted',
            model: null,
            meta: ['station_uuid' => $event->stationUuid],
            actorId: $event->actorId
        );
    }
}
