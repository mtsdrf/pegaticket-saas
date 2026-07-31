<?php

namespace App\Listeners\Functionality;

use App\Events\Functionality\FunctionalityDeleted;
use App\Models\AuditLog;

class AuditFunctionalityDeleted
{
    public function handle(FunctionalityDeleted $event): void
    {
        AuditLog::record(
            event: 'functionality_deleted',
            model: null,
            meta: [
                'functionality_uuid' => $event->functionalityUuid,
            ],
            actorId: $event->actorId
        );
    }
}