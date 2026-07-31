<?php

namespace App\Listeners\User;

use App\Events\User\UserCreated;
use App\Models\AuditLog;

class AuditUserCreated
{
    public function handle(UserCreated $event): void
    {
        AuditLog::record(
            event: 'user_created',
            model: null,
            meta: ['user_uuid' => $event->userUuid],
            actorId: $event->actorId
        );
    }
}