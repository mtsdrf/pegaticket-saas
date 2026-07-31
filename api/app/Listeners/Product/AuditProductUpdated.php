<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductUpdated;
use App\Models\AuditLog;

class AuditProductUpdated
{
    public function handle(ProductUpdated $event): void
    {
        AuditLog::record(
            event: 'product_updated',
            model: null,
            meta: [
                'product_uuid' => $event->productUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
