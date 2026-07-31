<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientCategoryCreated;
use App\Models\AuditLog;

class AuditClientCategoryCreated
{
    public function handle(ClientCategoryCreated $event): void
    {
        AuditLog::record(
            event: 'client_category_created',
            model: null,
            meta: ['client_category_uuid' => $event->clientCategoryUuid],
            actorId: $event->actorId
        );
    }
}
