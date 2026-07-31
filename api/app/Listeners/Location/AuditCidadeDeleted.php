<?php

namespace App\Listeners\Location;

use App\Events\Location\CidadeDeleted;
use App\Models\AuditLog;

class AuditCidadeDeleted
{
    public function handle(CidadeDeleted $event): void
    {
        AuditLog::record(
            event: 'cidade_deleted',
            model: null,
            meta: ['cidade_uuid' => $event->cidadeUuid],
            actorId: $event->actorId
        );
    }
}
