<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductCategoryDeleted;
use App\Models\AuditLog;

class AuditProductCategoryDeleted
{
    public function handle(ProductCategoryDeleted $event): void
    {
        AuditLog::record(
            event: 'product_category_deleted',
            model: null,
            meta: ['product_category_uuid' => $event->productCategoryUuid],
            actorId: $event->actorId
        );
    }
}
