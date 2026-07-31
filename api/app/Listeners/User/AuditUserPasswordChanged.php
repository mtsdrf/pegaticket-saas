<?php

namespace App\Listeners\User;

use App\Events\User\UserPasswordChanged;
use App\Models\AuditLog;

class AuditUserPasswordChanged
{
    public function handle(UserPasswordChanged $event): void
    {
        AuditLog::record(
            event: 'user_password_changed',
            model: null,
            meta: ['user_uuid' => $event->userUuid],
            actorId: $event->actorId
        );
    }
}
