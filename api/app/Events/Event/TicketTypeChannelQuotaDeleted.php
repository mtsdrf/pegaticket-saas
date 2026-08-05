<?php

namespace App\Events\Event;

class TicketTypeChannelQuotaDeleted
{
    public function __construct(
        public string $ticketTypeChannelQuotaUuid,
        public int $actorId
    ) {}
}
