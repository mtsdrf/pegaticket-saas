<?php

namespace App\Events\Legal;

class ReleaseNoteDeleted
{
    public function __construct(
        public string $releaseNoteUuid,
        public ?int $actorId
    ) {
    }
}
