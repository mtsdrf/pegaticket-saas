<?php

namespace App\Events\Event;

class EventCreated
{
    public function __construct(
        public string $eventUuid,
        public int $actorId
    ) {
    }
}
