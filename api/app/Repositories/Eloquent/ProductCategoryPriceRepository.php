<?php

namespace App\Repositories\Eloquent;

use App\Models\Product\ProductCategoryPrice;
use App\Repositories\Contracts\ProductCategoryPriceRepositoryInterface;
use Illuminate\Support\Collection;

class ProductCategoryPriceRepository extends BaseRepository implements ProductCategoryPriceRepositoryInterface
{
    public function __construct(ProductCategoryPrice $model)
    {
        parent::__construct($model);
    }

    public function listForProduct(int $productId): Collection
    {
        return $this->model
            ->where('product_id', $productId)
            ->with('clientCategory')
            ->orderBy('id')
            ->get();
    }

    /**
     * Substituição completa (sync): forceDelete()+recreate, sem soft
     * delete intermediário — ver comentário na migration sobre por que
     * isso não colide com a unique composta.
     */
    public function replaceForProduct(int $productId, array $rows): void
    {
        $this->model->where('product_id', $productId)->forceDelete();

        foreach ($rows as $row) {
            $this->model->newInstance()->create($row);
        }
    }
}
