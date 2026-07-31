<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientCategoriesSynced;
use App\Models\AuditLog;

class AuditClientCategoriesSynced
{
    public function handle(ClientCategoriesSynced $event): void
    {
        AuditLog::record(
            event: 'client_categories_synced',
            model: null,
            meta: [
                'client_uuid' => $event->clientUuid,
                'categories_in_payload' => $event->categoryUuids,
            ],
            actorId: $event->actorId
        );
    }
}
