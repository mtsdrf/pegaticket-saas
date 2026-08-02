<?php

namespace App\Events\Event;

class EventStatusChanged
{
    public function __construct(
        public string $eventUuid,
        public int $actorId,
        public string $fromStatus,
        public string $toStatus
    ) {
    }
}
