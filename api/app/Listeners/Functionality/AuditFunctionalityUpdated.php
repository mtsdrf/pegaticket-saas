<?php

namespace App\Listeners\Functionality;

use App\Events\Functionality\FunctionalityUpdated;
use App\Models\AuditLog;

class AuditFunctionalityUpdated
{
    public function handle(FunctionalityUpdated $event): void
    {
        AuditLog::record(
            event: 'functionality_updated',
            model: null,
            meta: [
                'functionality_uuid' => $event->functionalityUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}