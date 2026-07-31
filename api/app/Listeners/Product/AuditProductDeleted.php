<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductDeleted;
use App\Models\AuditLog;

class AuditProductDeleted
{
    public function handle(ProductDeleted $event): void
    {
        AuditLog::record(
            event: 'product_deleted',
            model: null,
            meta: ['product_uuid' => $event->productUuid],
            actorId: $event->actorId
        );
    }
}
