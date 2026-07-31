<?php

namespace App\Listeners\User;

use App\Events\User\UserProfileUpdated;
use App\Models\AuditLog;

class AuditUserProfileUpdated
{
    public function handle(UserProfileUpdated $event): void
    {
        AuditLog::record(
            event: 'user_profile_updated',
            model: null,
            meta: [
                'user_uuid' => $event->userUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
