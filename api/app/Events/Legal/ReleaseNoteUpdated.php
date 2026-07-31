<?php

namespace App\Events\Legal;

class ReleaseNoteUpdated
{
    public function __construct(
        public string $releaseNoteUuid,
        public ?int $actorId,
        public array $changes
    ) {
    }
}
