<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductTypeRequest;
use App\Http\Resources\Product\ProductTypeResource;
use App\Services\APIResponse;
use App\Services\Product\ProductTypeService;

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
                ],
            ]
        );
    }
}
