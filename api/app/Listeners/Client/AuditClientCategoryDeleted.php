<?php

namespace App\Listeners\Client;

use App\Events\Client\ClientCategoryDeleted;
use App\Models\AuditLog;

class AuditClientCategoryDeleted
{
    public function handle(ClientCategoryDeleted $event): void
    {
        AuditLog::record(
            event: 'client_category_deleted',
            model: null,
            meta: ['client_category_uuid' => $event->clientCategoryUuid],
            actorId: $event->actorId
        );
    }
}
