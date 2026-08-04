<?php

namespace App\Events\Event;

class EventGateDeleted
{
    public function __construct(
        public string $eventGateUuid,
        public int $actorId
    ) {}
}
