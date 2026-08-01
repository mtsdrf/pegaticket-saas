<?php

namespace App\Http\Controllers\Sale;

use App\DTOs\Sale\CreateSaleInstallmentDTO;
use App\DTOs\Sale\ReallocateSaleInstallmentsDTO;
use App\DTOs\Sale\UpdateSaleInstallmentDTO;
use App\Exceptions\InvalidSaleStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\ReallocateSaleInstallmentsRequest;
use App\Http\Requests\Sale\StoreSaleInstallmentRequest;
use App\Http\Requests\Sale\UpdateSaleInstallmentRequest;
use App\Http\Resources\Sale\SaleInstallmentResource;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use App\Services\APIResponse;
use App\Services\Sale\SaleInstallmentService;

/**
 * Sub-recurso de Sale (gestão manual de parcela), controller próprio —
 * mesmo padrão já usado em TenantRolePermissionController pra sub-recurso
 * aninhado com Service dedicado, em vez de inchar SaleController/
 * SaleService (já o módulo mais complexo do projeto).
 */
class SaleInstallmentController extends Controller
{
    public function __construct(
        private SaleInstallmentService $service
    ) {
    }

    public function store(StoreSaleInstallmentRequest $request, Sale $order)
    {
        $dto = CreateSaleInstallmentDTO::fromArray($request->validated());

        try {
            $installment = $this->service->create($order, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new SaleInstallmentResource($installment),
            __('messages.order.installment_created'),
            201
        );
    }

    public function update(UpdateSaleInstallmentRequest $request, Sale $order, SaleInstallment $installment)
    {
        $dto = UpdateSaleInstallmentDTO::fromArray($request->validated());

        try {
            $installment = $this->service->update($order, $installment, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new SaleInstallmentResource($installment),
            __('messages.order.installment_updated')
        );
    }

    public function destroy(Sale $order, SaleInstallment $installment)
    {
        try {
            $this->service->delete($order, $installment);
        } catch (InvalidSaleStateException $e) {
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
     * intermediário). Ver SaleInstallmentService::reallocate().
     */
    public function reallocate(ReallocateSaleInstallmentsRequest $request, Sale $order)
    {
        $dto = ReallocateSaleInstallmentsDTO::fromArray($request->validated());

        try {
            $installments = $this->service->reallocate($order, $dto->installments);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            SaleInstallmentResource::collection($installments),
            __('messages.order.installments_reallocated')
        );
    }
}
