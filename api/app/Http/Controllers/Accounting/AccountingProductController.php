<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Product\UpdateProductDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\UpdateAccountingProductFiscalRequest;
use App\Http\Requests\Product\ListProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\AuditLog;
use App\Models\Product\Product;
use App\Services\APIResponse;
use App\Services\Product\ProductService;

class AccountingProductController extends Controller
{
    public function __construct(
        private ProductService $service
    ) {
    }

    public function index(ListProductRequest $request)
    {
        $tenantId = app('tenant_id');
        $validated = $request->validated();

        $filters = collect($validated)->only([
            'q',
            'name',
            'barcode',
            'product_type_uuid',
            'product_category_uuid',
            'product_type_name',
            'product_category_name',
            'price_min',
            'price_max',
            'is_available',
        ])->all();

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) ($validated['per_page'] ?? 15),
            $validated['sort_by'] ?? null,
            $validated['sort_dir'] ?? 'asc'
        );

        return APIResponse::success(
            ProductResource::collection($list),
            __('messages.product.list'),
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

    public function updateFiscal(UpdateAccountingProductFiscalRequest $request, Product $product)
    {
        $dto = UpdateProductDTO::fromArray($request->validated());
        $product = $this->service->update($product, $dto);
        $product->load(ProductService::EAGER_RELATIONS);
        $product->loadSum('stockBalances', 'quantity_on_hand');

        $office = accounting_office();

        AuditLog::recordForNonUser('accounting_office.updated_product_fiscal', [
            'accounting_office_id' => $office?->id,
            'accounting_office_uuid' => $office?->uuid,
            'tenant_id' => app('tenant_id'),
            'product_uuid' => $product->uuid,
            'changed_fields' => array_keys($request->validated()),
        ]);

        return APIResponse::success(
            new ProductResource($product),
            __('messages.product.updated')
        );
    }
}
