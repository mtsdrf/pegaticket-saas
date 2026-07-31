<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\TableUpdated;
use App\Models\AuditLog;

class AuditTableUpdated
{
    public function handle(TableUpdated $event): void
    {
        AuditLog::record(
            event: 'table_updated',
            model: null,
            meta: ['table_uuid' => $event->tableUuid, 'changes' => $event->changes],
            actorId: $event->actorId
        );
    }
}
