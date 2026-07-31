<?php

namespace App\Listeners\Client;

use App\Events\Client\DiaIdealDeleted;
use App\Models\AuditLog;

class AuditDiaIdealDeleted
{
    public function handle(DiaIdealDeleted $event): void
    {
        AuditLog::record(
            event: 'dia_ideal_deleted',
            model: null,
            meta: ['dia_ideal_uuid' => $event->diaIdealUuid],
            actorId: $event->actorId
        );
    }
}
