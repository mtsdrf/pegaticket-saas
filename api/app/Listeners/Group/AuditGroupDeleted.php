<?php

namespace App\Listeners\Group;

use App\Events\Group\GroupDeleted;
use App\Models\AuditLog;

class AuditGroupDeleted
{
    public function handle(GroupDeleted $event): void
    {
        AuditLog::record(
            event: 'group_deleted',
            model: null,
            meta: ['group_uuid' => $event->groupUuid],
            actorId: $event->actorId
        );
    }
}