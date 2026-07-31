<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductCategoryCreated;
use App\Models\AuditLog;

class AuditProductCategoryCreated
{
    public function handle(ProductCategoryCreated $event): void
    {
        AuditLog::record(
            event: 'product_category_created',
            model: null,
            meta: ['product_category_uuid' => $event->productCategoryUuid],
            actorId: $event->actorId
        );
    }
}
