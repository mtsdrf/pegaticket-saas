<?php

namespace App\Events\Client;

class ClientUpdated
{
    public function __construct(
        public string $clientUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
