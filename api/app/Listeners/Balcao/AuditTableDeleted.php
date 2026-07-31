<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\TableDeleted;
use App\Models\AuditLog;

class AuditTableDeleted
{
    public function handle(TableDeleted $event): void
    {
        AuditLog::record(
            event: 'table_deleted',
            model: null,
            meta: ['table_uuid' => $event->tableUuid],
            actorId: $event->actorId
        );
    }
}
