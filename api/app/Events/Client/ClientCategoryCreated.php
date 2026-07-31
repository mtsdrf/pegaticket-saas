<?php

namespace App\Events\Client;

class ClientCategoryCreated
{
    public function __construct(
        public string $clientCategoryUuid,
        public int $actorId
    ) {
    }
}
