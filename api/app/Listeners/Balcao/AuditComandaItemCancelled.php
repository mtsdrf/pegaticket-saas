<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaItemCancelled;
use App\Models\AuditLog;

class AuditComandaItemCancelled
{
    public function handle(ComandaItemCancelled $event): void
    {
        AuditLog::record(
            event: 'comanda_item_cancelled',
            model: null,
            meta: [
                'comanda_uuid' => $event->comandaUuid,
                'item_uuid' => $event->itemUuid,
                'from_status' => $event->fromStatus,
                'reason' => $event->reason,
            ],
            actorId: $event->actorId
        );
    }
}
