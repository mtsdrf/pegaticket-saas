<?php

namespace App\Events\Event;

class EventGateUpdated
{
    public function __construct(
        public string $eventGateUuid,
        public int $actorId,
        public array $changes
    ) {}
}
