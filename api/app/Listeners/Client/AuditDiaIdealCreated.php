<?php

namespace App\Listeners\Client;

use App\Events\Client\DiaIdealCreated;
use App\Models\AuditLog;

class AuditDiaIdealCreated
{
    public function handle(DiaIdealCreated $event): void
    {
        AuditLog::record(
            event: 'dia_ideal_created',
            model: null,
            meta: ['dia_ideal_uuid' => $event->diaIdealUuid],
            actorId: $event->actorId
        );
    }
}
