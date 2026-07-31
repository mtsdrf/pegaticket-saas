<?php

namespace App\Events\Client;

class ClientCategoryDeleted
{
    public function __construct(
        public string $clientCategoryUuid,
        public int $actorId
    ) {
    }
}
