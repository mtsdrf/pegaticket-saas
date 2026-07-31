<?php

namespace App\Listeners\ApiKey;

use App\Events\ApiKey\ApiKeyCreated;
use App\Models\AuditLog;

class AuditApiKeyCreated
{
    public function handle(ApiKeyCreated $event): void
    {
        AuditLog::record(
            event: 'api_key_created',
            model: null,
            meta: [
                'api_key_uuid' => $event->apiKeyUuid,
                'tenant_id' => $event->tenantId,
            ],
            actorId: $event->actorId
        );
    }
}
