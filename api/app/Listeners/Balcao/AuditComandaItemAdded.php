<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaItemAdded;
use App\Models\AuditLog;

class AuditComandaItemAdded
{
    public function handle(ComandaItemAdded $event): void
    {
        AuditLog::record(
            event: 'comanda_item_added',
            model: null,
            meta: ['comanda_uuid' => $event->comandaUuid, 'item_uuid' => $event->itemUuid],
            actorId: $event->actorId
        );
    }
}
