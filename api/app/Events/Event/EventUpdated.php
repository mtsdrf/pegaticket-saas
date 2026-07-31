<?php

namespace App\Events\Event;

class EventUpdated
{
    public function __construct(
        public string $eventUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
