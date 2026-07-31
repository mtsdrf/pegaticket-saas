<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ProductCategoryPriceRepositoryInterface extends BaseRepositoryInterface
{
    public function listForProduct(int $productId): Collection;

    /**
     * @param array<int, array{tenant_id: int, product_id: int, client_category_id: int, price: mixed}> $rows
     */
    public function replaceForProduct(int $productId, array $rows): void;
}
