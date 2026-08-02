<?php

namespace App\Http\Controllers\Sale;

use App\DTOs\Sale\CancelSaleDTO;
use App\DTOs\Sale\CreateSaleDTO;
use App\Exceptions\DiscountLimitExceededException;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Exceptions\InvalidSaleStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\CancelSaleRequest;
use App\Http\Requests\Sale\PayInstallmentRequest;
use App\Http\Requests\Sale\PaySaleRequest;
use App\Http\Requests\Sale\RejectSaleRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleItemsRequest;
use App\DTOs\Sale\UpdateSaleItemsDTO;
use App\Http\Resources\Sale\SaleListResource;
use App\Http\Resources\Sale\SalePaymentResource;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use App\Services\APIResponse;
use App\Services\Sale\SalePaymentService;
use App\Services\Sale\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    private const EAGER_RELATIONS = SaleService::EAGER_RELATIONS;

    public function __construct(
        private SaleService $service,
        private SalePaymentService $paymentService,
    ) {
    }

    /**
     * Cria uma cobrança Pix para o pedido (roadmap 2A — recebimento do
     * tenant). Reaproveita perm:sales,update. Rejeita cobrança duplicada
     * ativa, pedido já pago ou cancelado (INVALID_ORDER_STATE / 422).
     */
    public function paymentCharge(Sale $sale)
    {
        try {
            $payment = $this->paymentService->createPixChargeForOrder($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new SalePaymentResource($payment),
            __('messages.sale.payment_charge_created'),
            201
        );
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');

        $filters = $request->only([
            'client_uuid',
            'client_name',
            'total_amount_min',
            'total_amount_max',
            'is_paid',
            'is_completed',
            'is_installment',
            'is_cancelled',
            'status',
            'origin',
            'active_only',
            'stage',
        ]);

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'desc')
        );

        return APIResponse::success(
            SaleListResource::collection($list),
            __('messages.sale.list'),
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

    public function show(Sale $sale)
    {
        $sale = $this->service->find($sale);
        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.show')
        );
    }

    public function store(StoreSaleRequest $request)
    {
        $dto = CreateSaleDTO::fromArray($request->validated(), app('tenant_id'));

        try {
            $sale = $this->service->create($dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.created'),
            201
        );
    }

    public function updateItems(UpdateSaleItemsRequest $request, Sale $sale)
    {
        $dto = UpdateSaleItemsDTO::fromArray($request->validated());

        try {
            $sale = $this->service->updateItems($sale, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.items_updated')
        );
    }

    public function complete(Sale $sale)
    {
        try {
            $sale = $this->service->complete($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.completed')
        );
    }

    /**
     * Igual a index(), mas força origin=storefront (nunca lido do
     * request) — usado só pela tela dedicada de gestão de vendas online
     * (perm:storefront-sales,read), independente da permissão sales,read.
     */
    public function indexStorefront(Request $request)
    {
        $tenantId = app('tenant_id');

        $filters = $request->only([
            'is_paid',
            'is_completed',
            'is_installment',
            'is_cancelled',
            'status',
        ]);
        $filters['origin'] = 'storefront';

        // Aditivo: só filtra "somente ativos" quando o parâmetro vier no
        // request, senão o endpoint segue igual pros demais consumidores.
        if ($request->has('active_only')) {
            $filters['active_only'] = $request->boolean('active_only');
        }

        $list = $this->service->paginate(
            $tenantId,
            $filters,
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'desc')
        );

        return APIResponse::success(
            SaleListResource::collection($list),
            __('messages.sale.list'),
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

    public function reopen(Sale $sale)
    {
        try {
            $sale = $this->service->reopen($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.reopened')
        );
    }

    public function pay(PaySaleRequest $request, Sale $sale)
    {
        $amount = $request->filled('amount') ? (float) $request->input('amount') : null;

        try {
            $sale = $this->service->pay($sale, $request->input('paid_at'), $amount);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            $sale->is_paid ? __('messages.sale.paid') : __('messages.sale.partially_paid')
        );
    }

    public function unpay(Sale $sale)
    {
        try {
            $sale = $this->service->unpay($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.unpaid')
        );
    }

    public function payInstallment(PayInstallmentRequest $request, Sale $sale, SaleInstallment $installment)
    {
        try {
            $sale = $this->service->payInstallment($sale, $installment, $request->input('paid_at'));
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.installment_paid')
        );
    }

    public function unpayInstallment(Sale $sale, SaleInstallment $installment)
    {
        try {
            $sale = $this->service->unpayInstallment($sale, $installment);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.installment_unpaid')
        );
    }

    public function cancel(CancelSaleRequest $request, Sale $sale)
    {
        $dto = CancelSaleDTO::fromArray($request->validated());

        try {
            $sale = $this->service->cancel($sale, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.cancelled')
        );
    }

    public function approve(Sale $sale)
    {
        try {
            $sale = $this->service->approve($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.approved')
        );
    }

    /**
     * Gera um link temporário de preparo (roadmap Loja) — token curto que
     * expira em 15 min, aberto sem login pelo celular via QR code. Só a
     * tela de gestão de vendas online usa (perm:storefront-sales,read).
     * Devolve o token cru (o único momento em que ele existe em claro pro
     * frontend montar a URL do QR).
     */
    public function prepLink(Sale $sale)
    {
        $sale = $this->service->find($sale);

        $prepLink = $this->service->generatePrepLink($sale);

        return APIResponse::success(
            [
                'token' => $prepLink->token,
                'expires_at' => $prepLink->expires_at,
            ],
            __('messages.sale.prep_link_generated'),
            201
        );
    }

    public function reject(RejectSaleRequest $request, Sale $sale)
    {
        try {
            $sale = $this->service->reject($sale, $request->input('reason'));
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.rejected')
        );
    }

    public function approveCancellation(Sale $sale)
    {
        try {
            $sale = $this->service->approveCancellationRequest($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.cancellation_approved')
        );
    }

    public function rejectCancellation(Sale $sale)
    {
        try {
            $sale = $this->service->rejectCancellationRequest($sale);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $sale->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($sale),
            __('messages.sale.cancellation_rejected')
        );
    }
}
