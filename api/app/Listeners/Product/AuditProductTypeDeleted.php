<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductTypeDeleted;
use App\Models\AuditLog;

class AuditProductTypeDeleted
{
    public function handle(ProductTypeDeleted $event): void
    {
        AuditLog::record(
            event: 'product_type_deleted',
            model: null,
            meta: ['product_type_uuid' => $event->productTypeUuid],
            actorId: $event->actorId
        );
    }
}
