<?php

namespace App\Events\Ticket;

class TicketResent
{
    public function __construct(
        public string $ticketUuid,
        public int $actorId
    ) {
    }
}
