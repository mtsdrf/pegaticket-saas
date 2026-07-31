<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientCategoryUpdated;
use App\Models\AuditLog;

class AuditClientCategoryUpdated
{
    public function handle(ClientCategoryUpdated $event): void
    {
        AuditLog::record(
            event: 'client_category_updated',
            model: null,
            meta: [
                'client_category_uuid' => $event->clientCategoryUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
