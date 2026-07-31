<?php

namespace App\Listeners\Client;

use App\Events\Client\DiaIdealUpdated;
use App\Models\AuditLog;

class AuditDiaIdealUpdated
{
    public function handle(DiaIdealUpdated $event): void
    {
        AuditLog::record(
            event: 'dia_ideal_updated',
            model: null,
            meta: [
                'dia_ideal_uuid' => $event->diaIdealUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
