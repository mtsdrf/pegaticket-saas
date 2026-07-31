<?php

namespace App\Events\Event;

class EventProductDeleted
{
    public function __construct(
        public string $eventProductUuid,
        public int $actorId
    ) {
    }
}
