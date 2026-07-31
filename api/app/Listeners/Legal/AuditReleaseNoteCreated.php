<?php

namespace App\Listeners\Legal;

use App\Events\Legal\ReleaseNoteCreated;
use App\Models\AuditLog;

class AuditReleaseNoteCreated
{
    public function handle(ReleaseNoteCreated $event): void
    {
        AuditLog::record(
            event: 'release_note_created',
            model: null,
            meta: ['release_note_uuid' => $event->releaseNoteUuid],
            actorId: $event->actorId
        );
    }
}
