<?php

namespace App\Events\Event;

class EventCategoryCreated
{
    public function __construct(
        public string $eventCategoryUuid,
        public int $actorId
    ) {
    }
}
