<?php

namespace App\Events\Event;

class TicketBatchUpdated
{
    public function __construct(
        public string $ticketBatchUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
