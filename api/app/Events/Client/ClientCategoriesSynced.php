<?php

namespace App\Events\Client;

class ClientCategoriesSynced
{
    public function __construct(
        public string $clientUuid,
        public array $categoryUuids,
        public int $actorId
    ) {
    }
}
