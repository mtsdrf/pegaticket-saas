<?php

namespace App\Events\Event;

class TicketBatchCreated
{
    public function __construct(
        public string $ticketBatchUuid,
        public int $actorId
    ) {
    }
}
