<?php

namespace App\Events\Ticket;

class TicketTransferred
{
    public function __construct(
        public string $ticketUuid,
        public ?string $previousAttendeeName,
        public string $newAttendeeName,
        public ?string $finalCustomerUuid,
    ) {}
}
