<?php

namespace App\Http\Controllers\Order;

use App\DTOs\Order\CreateOrderInstallmentDTO;
use App\DTOs\Order\ReallocateOrderInstallmentsDTO;
use App\DTOs\Order\UpdateOrderInstallmentDTO;
use App\Exceptions\InvalidOrderStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ReallocateOrderInstallmentsRequest;
use App\Http\Requests\Order\StoreOrderInstallmentRequest;
use App\Http\Requests\Order\UpdateOrderInstallmentRequest;
use App\Http\Resources\Order\OrderInstallmentResource;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Services\APIResponse;
use App\Services\Order\OrderInstallmentService;

/**
 * Sub-recurso de Order (gestão manual de parcela), controller próprio —
 * mesmo padrão já usado em TenantRolePermissionController pra sub-recurso
 * aninhado com Service dedicado, em vez de inchar OrderController/
 * OrderService (já o módulo mais complexo do projeto).
 */
class OrderInstallmentController extends Controller
{
    public function __construct(
        private OrderInstallmentService $service
    ) {
    }

    public function store(StoreOrderInstallmentRequest $request, Order $order)
    {
        $dto = CreateOrderInstallmentDTO::fromArray($request->validated());

        try {
            $installment = $this->service->create($order, $dto);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new OrderInstallmentResource($installment),
            __('messages.order.installment_created'),
            201
        );
    }

    public function update(UpdateOrderInstallmentRequest $request, Order $order, OrderInstallment $installment)
    {
        $dto = UpdateOrderInstallmentDTO::fromArray($request->validated());

        try {
            $installment = $this->service->update($order, $installment, $dto);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new OrderInstallmentResource($installment),
            __('messages.order.installment_updated')
        );
    }

    public function destroy(Order $order, OrderInstallment $installment)
    {
        try {
            $this->service->delete($order, $installment);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            null,
            __('messages.order.installment_deleted'),
            204
        );
    }

    /**
     * Substituição em lote das parcelas não pagas — caminho recomendado
     * pro frontend pra qualquer edição que envolva redistribuir valor
     * entre parcelas (os 3 endpoints acima só validam soma a cada
     * chamada isolada, o que torna redistribuição impossível sem 422
     * intermediário). Ver OrderInstallmentService::reallocate().
     */
    public function reallocate(ReallocateOrderInstallmentsRequest $request, Order $order)
    {
        $dto = ReallocateOrderInstallmentsDTO::fromArray($request->validated());

        try {
            $installments = $this->service->reallocate($order, $dto->installments);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            OrderInstallmentResource::collection($installments),
            __('messages.order.installments_reallocated')
        );
    }
}
