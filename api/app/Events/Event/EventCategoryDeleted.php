<?php

namespace App\Events\Event;

class EventCategoryDeleted
{
    public function __construct(
        public string $eventCategoryUuid,
        public int $actorId
    ) {
    }
}
