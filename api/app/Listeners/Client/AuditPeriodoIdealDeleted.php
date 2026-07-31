<?php

namespace App\Listeners\Client;

use App\Events\Client\PeriodoIdealDeleted;
use App\Models\AuditLog;

class AuditPeriodoIdealDeleted
{
    public function handle(PeriodoIdealDeleted $event): void
    {
        AuditLog::record(
            event: 'periodo_ideal_deleted',
            model: null,
            meta: ['periodo_ideal_uuid' => $event->periodoIdealUuid],
            actorId: $event->actorId
        );
    }
}
