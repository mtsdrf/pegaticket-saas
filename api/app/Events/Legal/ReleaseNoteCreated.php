<?php

namespace App\Events\Legal;

class ReleaseNoteCreated
{
    public function __construct(
        public string $releaseNoteUuid,
        public ?int $actorId
    ) {
    }
}
