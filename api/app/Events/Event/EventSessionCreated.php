<?php

namespace App\Events\Event;

class EventSessionCreated
{
    public function __construct(
        public string $eventSessionUuid,
        public int $actorId
    ) {
    }
}
