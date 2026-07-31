<?php

namespace App\Events\Event;

class TicketTypeDeleted
{
    public function __construct(
        public string $ticketTypeUuid,
        public int $actorId
    ) {
    }
}
