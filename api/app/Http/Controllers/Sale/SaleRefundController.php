<?php

namespace App\Http\Controllers\Sale;

use App\DTOs\Sale\CreateSaleRefundDTO;
use App\Exceptions\InvalidSaleStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRefundRequest;
use App\Http\Resources\Sale\SaleRefundResource;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleRefund;
use App\Services\APIResponse;
use App\Services\Media\MediaStorageService;
use App\Services\Sale\SaleRefundService;

/**
 * Sub-recurso de Sale (registro de estorno externo, spec 5.14) — mesmo
 * padrão de controller dedicado já usado em SaleInstallmentController.
 */
class SaleRefundController extends Controller
{
    public function __construct(
        private SaleRefundService $service,
        private MediaStorageService $mediaStorage,
    ) {
    }

    public function index(Sale $sale)
    {
        $refunds = $this->service->listForOrder($sale);

        return APIResponse::success(
            SaleRefundResource::collection($refunds),
            __('messages.order.refund_list')
        );
    }

    public function store(StoreSaleRefundRequest $request, Sale $sale)
    {
        $dto = CreateSaleRefundDTO::fromArray($request->validated());

        try {
            $refund = $this->service->create($sale, $dto, $request->file('receipt'));
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        return APIResponse::success(
            new SaleRefundResource($refund),
            __('messages.order.refund_created'),
            201
        );
    }

    /**
     * Comprovante nunca é servido por URL pública direta — disco privado
     * (ver config/media.php 'sale_refund'), só acessível autenticado,
     * tenant-scoped e via perm:sale_refunds,read (mesma rota gate do
     * index()).
     */
    public function receipt(Sale $sale, SaleRefund $refund)
    {
        if ((int) $sale->tenant_id !== (int) app('tenant_id') || (int) $refund->order_id !== (int) $sale->id) {
            abort(404);
        }

        return $this->mediaStorage->publicMediaResponse(
            $refund->receipt_path,
            null,
            $refund->receipt_mime,
            'sale_refund'
        );
    }
}
