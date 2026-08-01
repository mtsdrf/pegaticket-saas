<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SaleRefundCreated;
use App\Models\AuditLog;

class AuditSaleRefundCreated
{
    public function handle(SaleRefundCreated $event): void
    {
        AuditLog::record(
            event: 'order_refund_created',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'sale_refund_uuid' => $event->saleRefundUuid,
                'type' => $event->type,
                'amount' => $event->amount,
                'release_seats' => $event->releaseSeats,
                'ticket_uuids' => $event->ticketUuids,
            ],
            actorId: $event->actorId
        );
    }
}
