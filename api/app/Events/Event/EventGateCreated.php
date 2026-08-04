<?php

namespace App\Events\Event;

class EventGateCreated
{
    public function __construct(
        public string $eventGateUuid,
        public int $actorId
    ) {}
}
