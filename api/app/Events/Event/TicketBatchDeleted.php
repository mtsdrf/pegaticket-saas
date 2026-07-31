<?php

namespace App\Events\Event;

class TicketBatchDeleted
{
    public function __construct(
        public string $ticketBatchUuid,
        public int $actorId
    ) {
    }
}
