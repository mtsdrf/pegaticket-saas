<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderPaymentCharged;
use App\Models\AuditLog;

class AuditOrderPaymentCharged
{
    public function handle(OrderPaymentCharged $event): void
    {
        AuditLog::record(
            event: 'order_payment_charged',
            model: null,
            meta: [
                'order_uuid' => $event->orderUuid,
                'payment_uuid' => $event->paymentUuid,
                'method' => $event->method,
            ],
            actorId: $event->actorId
        );
    }
}
