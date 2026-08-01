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
    public function paymentCharge(Sale $order)
    {
        try {
            $payment = $this->paymentService->createPixChargeForOrder($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new SalePaymentResource($payment),
            __('messages.order.payment_charge_created'),
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
            'is_delivered',
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
            __('messages.order.list'),
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

    public function show(Sale $order)
    {
        $order = $this->service->find($order);
        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.show')
        );
    }

    public function store(StoreSaleRequest $request)
    {
        $dto = CreateSaleDTO::fromArray($request->validated(), app('tenant_id'));

        try {
            $order = $this->service->create($dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.created'),
            201
        );
    }

    public function updateItems(UpdateSaleItemsRequest $request, Sale $order)
    {
        $dto = UpdateSaleItemsDTO::fromArray($request->validated());

        try {
            $order = $this->service->updateItems($order, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.items_updated')
        );
    }

    public function deliver(Sale $order)
    {
        try {
            $order = $this->service->deliver($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.delivered')
        );
    }

    /**
     * "Saiu para entrega" — só usado pelas rotas /storefront-sales/*
     * (tela dedicada de gestão de vendas online).
     */
    public function dispatch(Sale $order)
    {
        try {
            $order = $this->service->dispatch($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.dispatched')
        );
    }

    /**
     * Desfaz "saiu para entrega" — só usado pelas rotas
     * /storefront-sales/* (tela dedicada de gestão de vendas online).
     */
    public function undispatch(Sale $order)
    {
        try {
            $order = $this->service->undispatch($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.undispatched')
        );
    }

    /**
     * Igual a index(), mas força origin=storefront (nunca lido do
     * request) — usado só pela tela dedicada de gestão de vendas online
     * (perm:storefront-sales,read), independente da permissão orders,read.
     */
    public function indexStorefront(Request $request)
    {
        $tenantId = app('tenant_id');

        $filters = $request->only([
            'is_paid',
            'is_delivered',
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
            __('messages.order.list'),
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

    public function undeliver(Sale $order)
    {
        try {
            $order = $this->service->undeliver($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.undelivered')
        );
    }

    public function pay(PaySaleRequest $request, Sale $order)
    {
        $amount = $request->filled('amount') ? (float) $request->input('amount') : null;

        try {
            $order = $this->service->pay($order, $request->input('paid_at'), $amount);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            $order->is_paid ? __('messages.order.paid') : __('messages.order.partially_paid')
        );
    }

    public function unpay(Sale $order)
    {
        try {
            $order = $this->service->unpay($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.unpaid')
        );
    }

    public function payInstallment(PayInstallmentRequest $request, Sale $order, SaleInstallment $installment)
    {
        try {
            $order = $this->service->payInstallment($order, $installment, $request->input('paid_at'));
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.installment_paid')
        );
    }

    public function unpayInstallment(Sale $order, SaleInstallment $installment)
    {
        try {
            $order = $this->service->unpayInstallment($order, $installment);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.installment_unpaid')
        );
    }

    public function cancel(CancelSaleRequest $request, Sale $order)
    {
        $dto = CancelSaleDTO::fromArray($request->validated());

        try {
            $order = $this->service->cancel($order, $dto);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.cancelled')
        );
    }

    public function approve(Sale $order)
    {
        try {
            $order = $this->service->approve($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.approved')
        );
    }

    /**
     * Gera um link temporário de preparo (roadmap Loja) — token curto que
     * expira em 15 min, aberto sem login pelo celular via QR code. Só a
     * tela de gestão de vendas online usa (perm:storefront-sales,read).
     * Devolve o token cru (o único momento em que ele existe em claro pro
     * frontend montar a URL do QR).
     */
    public function prepLink(Sale $order)
    {
        $order = $this->service->find($order);

        $prepLink = $this->service->generatePrepLink($order);

        return APIResponse::success(
            [
                'token' => $prepLink->token,
                'expires_at' => $prepLink->expires_at,
            ],
            __('messages.order.prep_link_generated'),
            201
        );
    }

    public function reject(RejectSaleRequest $request, Sale $order)
    {
        try {
            $order = $this->service->reject($order, $request->input('reason'));
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.rejected')
        );
    }

    public function approveCancellation(Sale $order)
    {
        try {
            $order = $this->service->approveCancellationRequest($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.cancellation_approved')
        );
    }

    public function rejectCancellation(Sale $order)
    {
        try {
            $order = $this->service->rejectCancellationRequest($order);
        } catch (InvalidSaleStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new SaleResource($order),
            __('messages.order.cancellation_rejected')
        );
    }
}
