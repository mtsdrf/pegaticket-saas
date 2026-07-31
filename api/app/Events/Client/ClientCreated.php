<?php

namespace App\Events\Client;

class ClientCreated
{
    public function __construct(
        public string $clientUuid,
        public ?int $actorId
    ) {
    }
}
