<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaOpened;
use App\Models\AuditLog;

class AuditComandaOpened
{
    public function handle(ComandaOpened $event): void
    {
        AuditLog::record(
            event: 'comanda_opened',
            model: null,
            meta: ['comanda_uuid' => $event->comandaUuid, 'table_uuid' => $event->tableUuid],
            actorId: $event->actorId
        );
    }
}
