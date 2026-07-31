<?php

namespace App\Listeners\Legal;

use App\Events\Legal\ReleaseNoteDeleted;
use App\Models\AuditLog;

class AuditReleaseNoteDeleted
{
    public function handle(ReleaseNoteDeleted $event): void
    {
        AuditLog::record(
            event: 'release_note_deleted',
            model: null,
            meta: ['release_note_uuid' => $event->releaseNoteUuid],
            actorId: $event->actorId
        );
    }
}
