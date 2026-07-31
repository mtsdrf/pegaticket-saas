<?php

namespace App\Events\Event;

class EventDeleted
{
    public function __construct(
        public string $eventUuid,
        public int $actorId
    ) {
    }
}
