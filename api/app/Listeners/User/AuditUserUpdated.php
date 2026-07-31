<?php

namespace App\Listeners\User;

use App\Events\User\UserUpdated;
use App\Models\AuditLog;

class AuditUserUpdated
{
    public function handle(UserUpdated $event): void
    {
        AuditLog::record(
            event: 'user_updated',
            model: null,
            meta: [
                'user_uuid' => $event->userUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}