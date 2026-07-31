<?php

namespace App\Listeners\Report;

use App\Events\Report\ReceivableInteractionCreated;
use App\Models\AuditLog;

class AuditReceivableInteractionCreated
{
    public function handle(ReceivableInteractionCreated $event): void
    {
        AuditLog::record(
            event: 'receivable_interaction_created',
            model: null,
            meta: [
                'interaction_uuid' => $event->interactionUuid,
                'order_uuid' => $event->orderUuid,
                'installment_uuid' => $event->installmentUuid,
                'interaction_type' => $event->interactionType,
            ],
            actorId: $event->actorId
        );
    }
}
