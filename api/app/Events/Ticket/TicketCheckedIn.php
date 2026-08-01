<?php

namespace App\Events\Ticket;

class TicketCheckedIn
{
    public function __construct(
        public ?string $ticketUuid,
        public string $result,
        public ?int $actorId
    ) {
    }
}
