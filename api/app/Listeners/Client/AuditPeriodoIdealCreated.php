<?php

namespace App\Listeners\Client;

use App\Events\Client\PeriodoIdealCreated;
use App\Models\AuditLog;

class AuditPeriodoIdealCreated
{
    public function handle(PeriodoIdealCreated $event): void
    {
        AuditLog::record(
            event: 'periodo_ideal_created',
            model: null,
            meta: ['periodo_ideal_uuid' => $event->periodoIdealUuid],
            actorId: $event->actorId
        );
    }
}
