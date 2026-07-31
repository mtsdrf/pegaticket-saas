<?php

namespace App\Events\Client;

class ClientDeleted
{
    public function __construct(
        public string $clientUuid,
        public int $actorId
    ) {
    }
}
