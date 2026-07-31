<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaItemPrepStatusUpdated;
use App\Models\AuditLog;

class AuditComandaItemPrepStatusUpdated
{
    public function handle(ComandaItemPrepStatusUpdated $event): void
    {
        AuditLog::record(
            event: 'comanda_item_prep_status_updated',
            model: null,
            meta: [
                'comanda_uuid' => $event->comandaUuid,
                'item_uuid' => $event->itemUuid,
                'from_status' => $event->fromStatus,
                'to_status' => $event->toStatus,
            ],
            actorId: $event->actorId
        );
    }
}
