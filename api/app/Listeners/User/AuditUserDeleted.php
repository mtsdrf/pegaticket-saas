<?php

namespace App\Listeners\User;

use App\Events\User\UserDeleted;
use App\Models\AuditLog;

class AuditUserDeleted
{
    public function handle(UserDeleted $event): void
    {
        AuditLog::record(
            event: 'user_deleted',
            model: null,
            meta: ['user_uuid' => $event->userUuid],
            actorId: $event->actorId
        );
    }
}