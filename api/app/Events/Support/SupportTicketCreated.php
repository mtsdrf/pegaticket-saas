<?php

namespace App\Events\Support;

class SupportTicketCreated
{
    public function __construct(
        public string $ticketUuid,
        public int $tenantId,
        public int $actorId
    ) {
    }
}
