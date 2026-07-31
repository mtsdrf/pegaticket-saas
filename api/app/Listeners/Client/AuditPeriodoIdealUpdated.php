<?php

namespace App\Listeners\Client;

use App\Events\Client\PeriodoIdealUpdated;
use App\Models\AuditLog;

class AuditPeriodoIdealUpdated
{
    public function handle(PeriodoIdealUpdated $event): void
    {
        AuditLog::record(
            event: 'periodo_ideal_updated',
            model: null,
            meta: [
                'periodo_ideal_uuid' => $event->periodoIdealUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
