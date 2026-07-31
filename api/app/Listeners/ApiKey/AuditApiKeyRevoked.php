<?php

namespace App\Listeners\ApiKey;

use App\Events\ApiKey\ApiKeyRevoked;
use App\Models\AuditLog;

class AuditApiKeyRevoked
{
    public function handle(ApiKeyRevoked $event): void
    {
        AuditLog::record(
            event: 'api_key_revoked',
            model: null,
            meta: [
                'api_key_uuid' => $event->apiKeyUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
