<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductTypeUpdated;
use App\Models\AuditLog;

class AuditProductTypeUpdated
{
    public function handle(ProductTypeUpdated $event): void
    {
        AuditLog::record(
            event: 'product_type_updated',
            model: null,
            meta: [
                'product_type_uuid' => $event->productTypeUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
