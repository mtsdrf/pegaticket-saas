<?php

namespace App\Listeners\Group;

use App\Events\Group\GroupUpdated;
use App\Models\AuditLog;

class AuditGroupUpdated
{
    public function handle(GroupUpdated $event): void
    {
        AuditLog::record(
            event: 'group_updated',
            model: null,
            meta: [
                'group_uuid' => $event->groupUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}