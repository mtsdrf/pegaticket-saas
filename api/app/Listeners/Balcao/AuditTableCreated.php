<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\TableCreated;
use App\Models\AuditLog;

class AuditTableCreated
{
    public function handle(TableCreated $event): void
    {
        AuditLog::record(
            event: 'table_created',
            model: null,
            meta: ['table_uuid' => $event->tableUuid],
            actorId: $event->actorId
        );
    }
}
