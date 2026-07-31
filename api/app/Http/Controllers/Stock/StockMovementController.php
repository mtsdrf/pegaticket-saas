<?php

namespace App\Http\Controllers\Stock;

use App\DTOs\Stock\StockAdjustmentDTO;
use App\DTOs\Stock\StockMovementDTO;
use App\DTOs\Stock\StockReserveCancelDTO;
use App\DTOs\Stock\StockTransferDTO;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockMovementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockMovementAdjustmentRequest;
use App\Http\Requests\Stock\StoreStockMovementBlockRequest;
use App\Http\Requests\Stock\StoreStockMovementEntryRequest;
use App\Http\Requests\Stock\StoreStockMovementExitRequest;
use App\Http\Requests\Stock\StoreStockMovementLossRequest;
use App\Http\Requests\Stock\StoreStockMovementReserveCancelRequest;
use App\Http\Requests\Stock\StoreStockMovementReserveRequest;
use App\Http\Requests\Stock\StoreStockMovementReturnRequest;
use App\Http\Requests\Stock\StoreStockMovementTransferRequest;
use App\Http\Requests\Stock\StoreStockMovementUnblockRequest;
use App\Http\Resources\Stock\StockBalanceResource;
use App\Http\Resources\Stock\StockMovementResource;
use App\Models\Product\Product;
use App\Models\Stock\StockLocation;
use App\Models\Stock\StockMovement;
use App\Services\APIResponse;
use App\Services\Stock\StockService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    private const EAGER_RELATIONS = ['product', 'location', 'destinationLocation'];

    public function __construct(
        private StockService $service
    ) {
    }

    public function balances(Request $request)
    {
        $tenantId = app('tenant_id');

        $filters = $request->only([
            'product_uuid',
            'location_uuid',
            'product_name',
            'location_name',
            'quantity_on_hand_min',
            'quantity_on_hand_max',
            'quantity_reserved_min',
            'quantity_reserved_max',
            'quantity_blocked_min',
            'quantity_blocked_max',
            'quantity_available_min',
            'quantity_available_max',
        ]);

        $list = $this->service->paginateBalances(
            $tenantId,
            $filters,
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'desc')
        );

        return APIResponse::success(
            StockBalanceResource::collection($list),
            __('messages.stock.balances_list'),
            200,
            ['pagination' => $this->paginationMeta($list)]
        );
    }

    public function movements(Request $request)
    {
        $tenantId = app('tenant_id');

        $filters = $request->only([
            'product_uuid',
            'location_uuid',
            'product_name',
            'location_name',
            'type',
            'reason',
            'quantity_min',
            'quantity_max',
            'balance_after_min',
            'balance_after_max',
            'date_from',
            'date_to',
        ]);

        $list = $this->service->paginateMovements(
            $tenantId,
            $filters,
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'desc')
        );

        return APIResponse::success(
            StockMovementResource::collection($list),
            __('messages.stock.movements_list'),
            200,
            ['pagination' => $this->paginationMeta($list)]
        );
    }

    public function entry(StoreStockMovementEntryRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        $movement = $this->service->entry($product, $location, $dto->quantity, $dto->reason, $dto->notes, $dto->unitCost);
        $movement->load(self::EAGER_RELATIONS);

        return APIResponse::success(new StockMovementResource($movement), __('messages.stock.entry_created'), 201);
    }

    public function exit(StoreStockMovementExitRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->exit($product, $location, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.exit_created')
        );
    }

    public function returnMovement(StoreStockMovementReturnRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        $movement = $this->service->returnStock($product, $location, $dto->quantity, $dto->reason, $dto->notes);
        $movement->load(self::EAGER_RELATIONS);

        return APIResponse::success(new StockMovementResource($movement), __('messages.stock.return_created'), 201);
    }

    public function loss(StoreStockMovementLossRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->loss($product, $location, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.loss_created')
        );
    }

    public function adjustment(StoreStockMovementAdjustmentRequest $request)
    {
        $dto = StockAdjustmentDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->adjust($product, $location, $dto->quantity, $dto->direction, $dto->reason, $dto->notes),
            __('messages.stock.adjustment_created')
        );
    }

    public function transfer(StoreStockMovementTransferRequest $request)
    {
        $dto = StockTransferDTO::fromArray($request->validated());
        [$product, $from] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);
        $to = $this->resolveLocation($dto->destinationLocationUuid);

        return $this->handleMovement(
            fn() => $this->service->transfer($product, $from, $to, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.transfer_created')
        );
    }

    public function block(StoreStockMovementBlockRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->block($product, $location, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.block_created')
        );
    }

    public function unblock(StoreStockMovementUnblockRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->unblock($product, $location, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.unblock_created')
        );
    }

    public function reserve(StoreStockMovementReserveRequest $request)
    {
        $dto = StockMovementDTO::fromArray($request->validated());
        [$product, $location] = $this->resolveProductAndLocation($dto->productUuid, $dto->locationUuid);

        return $this->handleMovement(
            fn() => $this->service->reserve($product, $location, $dto->quantity, $dto->reason, $dto->notes),
            __('messages.stock.reserve_created')
        );
    }

    public function reserveCancel(StoreStockMovementReserveCancelRequest $request)
    {
        $dto = StockReserveCancelDTO::fromArray($request->validated());

        $original = StockMovement::where('uuid', $dto->movementUuid)
            ->where('tenant_id', app('tenant_id'))
            ->firstOrFail();

        return $this->handleMovement(
            fn() => $this->service->reserveCancel($original, $dto->notes),
            __('messages.stock.reserve_cancel_created')
        );
    }

    /**
     * @return array{0: Product, 1: StockLocation}
     */
    private function resolveProductAndLocation(string $productUuid, string $locationUuid): array
    {
        $tenantId = app('tenant_id');

        $product = Product::where('uuid', $productUuid)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $location = StockLocation::where('uuid', $locationUuid)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return [$product, $location];
    }

    private function resolveLocation(string $locationUuid): StockLocation
    {
        return StockLocation::where('uuid', $locationUuid)
            ->where('tenant_id', app('tenant_id'))
            ->firstOrFail();
    }

    private function handleMovement(\Closure $action, string $successMessage)
    {
        try {
            $movement = $action();
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $movement->load(self::EAGER_RELATIONS);

        return APIResponse::success(new StockMovementResource($movement), $successMessage, 201);
    }

    private function paginationMeta($list): array
    {
        return [
            'current_page' => $list->currentPage(),
            'per_page' => $list->perPage(),
            'total' => $list->total(),
            'last_page' => $list->lastPage(),
        ];
    }
}
