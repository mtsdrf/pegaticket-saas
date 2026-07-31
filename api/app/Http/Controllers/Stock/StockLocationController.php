<?php

namespace App\Http\Controllers\Stock;

use App\DTOs\Stock\CreateStockLocationDTO;
use App\DTOs\Stock\UpdateStockLocationDTO;
use App\Exceptions\DuplicateNameException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockLocationRequest;
use App\Http\Requests\Stock\UpdateStockLocationRequest;
use App\Http\Resources\Stock\StockLocationResource;
use App\Models\Stock\StockLocation;
use App\Services\APIResponse;
use App\Services\Stock\StockLocationService;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    public function __construct(
        private StockLocationService $service
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');

        $list = $this->service->paginate(
            $tenantId,
            (int) $request->get('per_page', 15),
            $request->only(['name', 'type', 'address', 'is_default', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            StockLocationResource::collection($list),
            __('messages.stock_location.list'),
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

    public function store(StoreStockLocationRequest $request)
    {
        $dto = CreateStockLocationDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $location = $this->service->create($dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new StockLocationResource($location),
            __('messages.stock_location.created'),
            201
        );
    }

    public function update(UpdateStockLocationRequest $request, StockLocation $stockLocation)
    {
        $dto = UpdateStockLocationDTO::fromArray($request->validated());

        try {
            $location = $this->service->update($stockLocation, $dto);
        } catch (DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_NAME');
        }

        return APIResponse::success(
            new StockLocationResource($location),
            __('messages.stock_location.updated')
        );
    }

    public function destroy(StockLocation $stockLocation)
    {
        $this->service->delete($stockLocation);

        return APIResponse::success(
            null,
            __('messages.stock_location.deleted'),
            204
        );
    }
}
