<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductCategoryUpdated;
use App\Models\AuditLog;

class AuditProductCategoryUpdated
{
    public function handle(ProductCategoryUpdated $event): void
    {
        AuditLog::record(
            event: 'product_category_updated',
            model: null,
            meta: [
                'product_category_uuid' => $event->productCategoryUuid,
                'changes' => $event->changes
            ],
            actorId: $event->actorId
        );
    }
}
