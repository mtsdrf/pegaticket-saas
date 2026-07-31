<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderPaymentRefundRequested;
use App\Models\AuditLog;

class AuditOrderPaymentRefundRequested
{
    public function handle(OrderPaymentRefundRequested $event): void
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
