<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaItemSentToStation;
use App\Models\AuditLog;

class AuditComandaItemSentToStation
{
    public function handle(ComandaItemSentToStation $event): void
    {
        AuditLog::record(
            event: 'comanda_item_sent_to_station',
            model: null,
            meta: [
                'comanda_uuid' => $event->comandaUuid,
                'item_uuid' => $event->itemUuid,
                'station_uuid' => $event->stationUuid,
            ],
            actorId: $event->actorId
        );
    }
}
