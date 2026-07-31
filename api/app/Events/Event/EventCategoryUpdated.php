<?php

namespace App\Events\Event;

class EventCategoryUpdated
{
    public function __construct(
        public string $eventCategoryUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
