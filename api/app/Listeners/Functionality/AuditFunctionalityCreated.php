<?php

namespace App\Listeners\Functionality;

use App\Events\Functionality\FunctionalityCreated;
use App\Models\AuditLog;

class AuditFunctionalityCreated
{
    public function handle(FunctionalityCreated $event): void
    {
        AuditLog::record(
            event: 'functionality_created',
            model: null,
            meta: [
                'functionality_uuid' => $event->functionalityUuid,
            ],
            actorId: $event->actorId
        );
    }
}
