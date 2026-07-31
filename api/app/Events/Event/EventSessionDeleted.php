<?php

namespace App\Events\Event;

class EventSessionDeleted
{
    public function __construct(
        public string $eventSessionUuid,
        public int $actorId
    ) {
    }
}
