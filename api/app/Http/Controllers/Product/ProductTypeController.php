<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductTypeRequest;
use App\Http\Requests\Product\StoreProductTypeRequest;
use App\Http\Requests\Product\UpdateProductTypeRequest;
use App\Http\Resources\Product\ProductTypeResource;
use App\Models\Product\ProductType;
use App\Services\APIResponse;
use App\Services\Product\ProductTypeService;
use App\DTOs\Product\CreateProductTypeDTO;
use App\DTOs\Product\UpdateProductTypeDTO;

class ProductTypeController extends Controller
{
    public function __construct(
        private ProductTypeService $service
    ) {
    }

    public function index(ListProductTypeRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'product_category_name',
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
            ProductTypeResource::collection($list),
            __('messages.product_type.list'),
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

    public function store(StoreProductTypeRequest $request)
    {
        $dto = CreateProductTypeDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $type = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ProductTypeResource($type),
            __('messages.product_type.created'),
            201
        );
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType)
    {
        $dto = UpdateProductTypeDTO::fromArray($request->validated());

        try {
            $type = $this->service->update($productType, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new ProductTypeResource($type),
            __('messages.product_type.updated')
        );
    }

    public function destroy(ProductType $productType)
    {
        $this->service->delete($productType);

        return APIResponse::success(
            null,
            __('messages.product_type.deleted'),
            204
        );
    }
}
