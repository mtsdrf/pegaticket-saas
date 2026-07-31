<?php

namespace App\Events\Event;

class EventProductUpdated
{
    public function __construct(
        public string $eventProductUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
