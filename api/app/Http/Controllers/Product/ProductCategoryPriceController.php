<?php

namespace App\Http\Controllers\Product;

use App\DTOs\Product\SyncProductCategoryPricesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SyncProductCategoryPricesRequest;
use App\Http\Resources\Product\ProductCategoryPriceResource;
use App\Models\Product\Product;
use App\Services\APIResponse;
use App\Services\Product\ProductCategoryPriceService;

class ProductCategoryPriceController extends Controller
{
    public function __construct(
        private ProductCategoryPriceService $service
    ) {
    }

    public function index(Product $product)
    {
        $prices = $this->service->list($product);

        return APIResponse::success(
            ProductCategoryPriceResource::collection($prices),
            __('messages.product_category_price.list')
        );
    }

    public function sync(SyncProductCategoryPricesRequest $request, Product $product)
    {
        $dto = SyncProductCategoryPricesDTO::fromArray($request->validated());

        $prices = $this->service->sync($product, $dto);

        return APIResponse::success(
            ProductCategoryPriceResource::collection($prices),
            __('messages.product_category_price.synced')
        );
    }
}
