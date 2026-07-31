<?php

namespace App\Listeners\User;

use App\Events\User\UserEmailChanged;
use App\Models\AuditLog;

class AuditUserEmailChanged
{
    public function handle(UserEmailChanged $event): void
    {
        AuditLog::record(
            event: 'user_email_changed',
            model: null,
            meta: [
                'user_uuid' => $event->userUuid,
                'old_email' => $event->oldEmail,
                'new_email' => $event->newEmail,
            ],
            actorId: $event->actorId
        );
    }
}
