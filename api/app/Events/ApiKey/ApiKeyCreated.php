<?php

namespace App\Events\ApiKey;

class ApiKeyCreated
{
    public function __construct(
        public string $apiKeyUuid,
        public int $tenantId,
        public int $actorId,
    ) {
    }
}
