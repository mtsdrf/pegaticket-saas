<?php

namespace App\Events\Event;

class TicketTypeCreated
{
    public function __construct(
        public string $ticketTypeUuid,
        public int $actorId
    ) {
    }
}
