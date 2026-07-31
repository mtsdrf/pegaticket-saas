<?php

namespace App\Listeners\Balcao;

use App\Events\Balcao\ComandaClosed;
use App\Models\AuditLog;

class AuditComandaClosed
{
    public function handle(ComandaClosed $event): void
    {
        AuditLog::record(
            event: 'comanda_closed',
            model: null,
            meta: [
                'comanda_uuid' => $event->comandaUuid,
                'order_uuid' => $event->orderUuid,
                'total' => $event->total,
                'service_fee' => $event->serviceFee,
                'payments' => $event->payments,
            ],
            actorId: $event->actorId
        );
    }
}
