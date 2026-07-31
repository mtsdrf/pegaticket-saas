<?php

namespace App\Events\Event;

class EventSessionUpdated
{
    public function __construct(
        public string $eventSessionUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
