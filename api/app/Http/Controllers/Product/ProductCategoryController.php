<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductCategoryRequest;
use App\Http\Requests\Product\StoreProductCategoryRequest;
use App\Http\Requests\Product\UpdateProductCategoryRequest;
use App\Http\Resources\Product\ProductCategoryResource;
use App\Models\Product\ProductCategory;
use App\Services\APIResponse;
use App\Services\Product\ProductCategoryService;
use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\UpdateProductCategoryDTO;

class ProductCategoryController extends Controller
{
    public function __construct(
        private ProductCategoryService $service
    ) {
    }

    public function index(ListProductCategoryRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'priority_min',
            'priority_max',
            'is_active',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            ProductCategoryResource::collection($list),
            __('messages.product_category.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $dto = CreateProductCategoryDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $category = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ProductCategoryResource($category),
            __('messages.product_category.created'),
            201
        );
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory)
    {
        $dto = UpdateProductCategoryDTO::fromArray($request->validated());

        try {
            $category = $this->service->update($productCategory, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ProductCategoryResource($category),
            __('messages.product_category.updated')
        );
    }

    public function destroy(ProductCategory $productCategory)
    {
        $this->service->delete($productCategory);

        return APIResponse::success(
            null,
            __('messages.product_category.deleted'),
            204
        );
    }
}
