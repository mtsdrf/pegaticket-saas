<?php

namespace App\Events\Support;

class HelpRequestCreated
{
    public function __construct(
        public string $ticketUuid,
        public int $tenantId,
        public int $actorId
    ) {
    }
}
