<?php

namespace App\Listeners\Sale;

use App\Events\Sale\SalePaymentRefundRequested;
use App\Models\AuditLog;

class AuditSalePaymentRefundRequested
{
    public function handle(SalePaymentRefundRequested $event): void
    {
        AuditLog::record(
            event: 'order_payment_refund_requested',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'refund_protocol' => $event->refundProtocol,
            ],
            actorId: $event->actorId
        );
    }
}
