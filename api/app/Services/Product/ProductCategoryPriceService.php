<?php

namespace App\Services\Product;

use App\DTOs\Product\SyncProductCategoryPricesDTO;
use App\Events\Product\ProductCategoryPricesSynced;
use App\Models\Client\ClientCategory;
use App\Models\Product\Product;
use App\Repositories\Contracts\ProductCategoryPriceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductCategoryPriceService
{
    public function __construct(
        private ProductCategoryPriceRepositoryInterface $repository
    ) {
    }

    public function list(Product $product): Collection
    {
        $this->assertBelongsToCurrentTenant($product);

        return $this->repository->listForProduct($product->id);
    }

    public function sync(Product $product, SyncProductCategoryPricesDTO $dto): Collection
    {
        $this->assertBelongsToCurrentTenant($product);

        return DB::transaction(function () use ($product, $dto) {
            $tenantId = (int) app('tenant_id');

            $rows = collect($dto->prices)->map(function (array $item) use ($tenantId, $product) {
                $categoryId = ClientCategory::where('uuid', $item['client_category_uuid'])
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->value('id');

                return [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'client_category_id' => $categoryId,
                    'price' => $item['price'],
                ];
            })->all();

            $this->repository->replaceForProduct($product->id, $rows);

            event(new ProductCategoryPricesSynced(
                productUuid: $product->uuid,
                categoryPrices: $dto->prices,
                actorId: Auth::id()
            ));

            return $this->repository->listForProduct($product->id);
        });
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant —
     * mesmo guard já usado em todo recurso tenant-scoped do projeto.
     */
    private function assertBelongsToCurrentTenant(Product $product): void
    {
        if ((int) $product->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
