<?php

namespace App\Events\Event;

class TicketTypeChannelQuotaUpdated
{
    public function __construct(
        public string $ticketTypeChannelQuotaUuid,
        public int $actorId,
        public array $changes
    ) {}
}
