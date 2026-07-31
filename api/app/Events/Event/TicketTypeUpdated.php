<?php

namespace App\Events\Event;

class TicketTypeUpdated
{
    public function __construct(
        public string $ticketTypeUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
