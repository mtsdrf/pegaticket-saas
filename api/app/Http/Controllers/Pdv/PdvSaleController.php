<?php

namespace App\Http\Controllers\Pdv;

use App\DTOs\Pdv\CreatePdvSaleDTO;
use App\Exceptions\DiscountLimitExceededException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidStockMovementException;
use App\Exceptions\Pdv\CashSessionException;
use App\Exceptions\Pdv\PaymentAmountMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdv\CreatePdvSaleRequest;
use App\Http\Resources\Order\OrderResource;
use App\Services\APIResponse;
use App\Services\Order\OrderService;
use App\Services\Pdv\PdvSaleService;

class PdvSaleController extends Controller
{
    private const EAGER_RELATIONS = [...OrderService::EAGER_RELATIONS, 'operator'];

    public function __construct(
        private PdvSaleService $service
    ) {
    }

    public function store(CreatePdvSaleRequest $request)
    {
        $validated = $request->validated();
        $dto = CreatePdvSaleDTO::fromArray($validated);

        try {
            $order = $this->service->create(
                app('tenant_id'),
                $dto,
                $validated['cash_session_uuid'] ?? null
            );
        } catch (CashSessionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'CASH_SESSION_ERROR');
        } catch (PaymentAmountMismatchException $e) {
            return APIResponse::error($e->getMessage(), 422, 'PAYMENT_AMOUNT_MISMATCH');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.pdv.sale_completed'),
            201
        );
    }
}
