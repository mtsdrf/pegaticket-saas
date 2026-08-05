<?php

namespace App\Events\Event;

class TicketTypeChannelQuotaCreated
{
    public function __construct(
        public string $ticketTypeChannelQuotaUuid,
        public int $actorId
    ) {}
}
