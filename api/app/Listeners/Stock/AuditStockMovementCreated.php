<?php

namespace App\Listeners\Stock;

use App\Events\Stock\StockMovementCreated;
use App\Models\AuditLog;

/**
 * O StockMovement em si já É o registro de auditoria (ledger append-only).
 * Este listener só espelha a criação em AuditLog para manter o mesmo
 * mecanismo de auditoria central usado no resto do projeto.
 */
class AuditStockMovementCreated
{
    public function handle(StockMovementCreated $event): void
    {
        AuditLog::record(
            event: 'stock_movement_created',
            model: null,
            meta: [
                'stock_movement_uuid' => $event->stockMovementUuid,
                'type' => $event->type,
                'ticket_type_uuid' => $event->ticketTypeUuid,
                'location_uuid' => $event->locationUuid,
                'quantity' => $event->quantity,
            ],
            actorId: $event->actorId
        );
    }
}
