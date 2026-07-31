<?php

namespace App\Events\Client;

class ClientCategoryUpdated
{
    public function __construct(
        public string $clientCategoryUuid,
        public int $actorId,
        public array $changes
    ) {
    }
}
