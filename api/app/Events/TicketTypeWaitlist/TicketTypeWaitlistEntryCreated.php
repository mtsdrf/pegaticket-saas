<?php

namespace App\Events\TicketTypeWaitlist;

class TicketTypeWaitlistEntryCreated
{
    public function __construct(
        public string $ticketTypeWaitlistEntryUuid,
        public string $ticketTypeUuid,
    ) {}
}
