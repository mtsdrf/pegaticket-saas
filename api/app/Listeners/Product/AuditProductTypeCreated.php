<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductTypeCreated;
use App\Models\AuditLog;

class AuditProductTypeCreated
{
    public function handle(ProductTypeCreated $event): void
    {
        AuditLog::record(
            event: 'product_type_created',
            model: null,
            meta: ['product_type_uuid' => $event->productTypeUuid],
            actorId: $event->actorId
        );
    }
}
