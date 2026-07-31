<?php

namespace App\Events\Event;

class EventProductCreated
{
    public function __construct(
        public string $eventProductUuid,
        public int $actorId
    ) {
    }
}
