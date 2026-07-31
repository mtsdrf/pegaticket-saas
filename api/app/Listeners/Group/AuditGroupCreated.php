<?php

namespace App\Listeners\Group;

use App\Events\Group\GroupCreated;
use App\Models\AuditLog;

class AuditGroupCreated
{
    public function handle(GroupCreated $event): void
    {
        AuditLog::record(
            event: 'group_created',
            model: null,
            meta: ['group_uuid' => $event->groupUuid],
            actorId: $event->actorId
        );
    }
}