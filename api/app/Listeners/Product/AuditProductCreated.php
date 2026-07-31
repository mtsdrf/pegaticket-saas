<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductCreated;
use App\Models\AuditLog;

class AuditProductCreated
{
    public function handle(ProductCreated $event): void
    {
        AuditLog::record(
            event: 'product_created',
            model: null,
            meta: ['product_uuid' => $event->productUuid],
            actorId: $event->actorId
        );
    }
}
