<?php

namespace App\Events\ApiKey;

class ApiKeyRevoked
{
    public function __construct(
        public string $apiKeyUuid,
        public int $tenantId,
        public int $actorId,
    ) {
    }
}
