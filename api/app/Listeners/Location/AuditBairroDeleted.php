<?php

namespace App\Listeners\Location;

use App\Events\Location\BairroDeleted;
use App\Models\AuditLog;

class AuditBairroDeleted
{
    public function handle(BairroDeleted $event): void
    {
        AuditLog::record(
            event: 'bairro_deleted',
            model: null,
            meta: ['bairro_uuid' => $event->bairroUuid],
            actorId: $event->actorId
        );
    }
}
