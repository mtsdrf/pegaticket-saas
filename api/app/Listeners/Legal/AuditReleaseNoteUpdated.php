<?php

namespace App\Listeners\Legal;

use App\Events\Legal\ReleaseNoteUpdated;
use App\Models\AuditLog;

class AuditReleaseNoteUpdated
{
    public function handle(ReleaseNoteUpdated $event): void
    {
        AuditLog::record(
            event: 'release_note_updated',
            model: null,
            meta: [
                'release_note_uuid' => $event->releaseNoteUuid,
                'changes' => $event->changes,
            ],
            actorId: $event->actorId
        );
    }
}
