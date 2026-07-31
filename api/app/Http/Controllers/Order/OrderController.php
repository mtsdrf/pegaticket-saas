<?php

namespace App\Http\Controllers\Order;

use App\DTOs\Order\CancelOrderDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\Exceptions\DiscountLimitExceededException;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidStockMovementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\PayInstallmentRequest;
use App\Http\Requests\Order\PayOrderRequest;
use App\Http\Requests\Order\RejectOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderItemsRequest;
use App\DTOs\Order\UpdateOrderItemsDTO;
use App\Http\Resources\Order\OrderListResource;
use App\Http\Resources\Order\OrderPaymentResource;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Services\APIResponse;
use App\Services\Order\OrderPaymentService;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const EAGER_RELATIONS = OrderService::EAGER_RELATIONS;

    public function __construct(
        private OrderService $service,
        private OrderPaymentService $paymentService,
    ) {
    }

    /**
     * Cria uma cobrança Pix para o pedido (roadmap 2A — recebimento do
     * tenant). Reaproveita perm:orders,update. Rejeita cobrança duplicada
     * ativa, pedido já pago ou cancelado (INVALID_ORDER_STATE / 422).
     */
    public function paymentCharge(Order $order)
    {
        try {
            $payment = $this->paymentService->createPixChargeForOrder($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new OrderPaymentResource($payment),
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
            OrderListResource::collection($list),
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

    public function show(Order $order)
    {
        $order = $this->service->find($order);
        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.show')
        );
    }

    public function store(StoreOrderRequest $request)
    {
        $dto = CreateOrderDTO::fromArray($request->validated(), app('tenant_id'));

        try {
            $order = $this->service->create($dto);
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
            __('messages.order.created'),
            201
        );
    }

    public function updateItems(UpdateOrderItemsRequest $request, Order $order)
    {
        $dto = UpdateOrderItemsDTO::fromArray($request->validated());

        try {
            $order = $this->service->updateItems($order, $dto);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        } catch (DiscountLimitExceededException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DISCOUNT_LIMIT_EXCEEDED');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.items_updated')
        );
    }

    public function deliver(Order $order)
    {
        try {
            $order = $this->service->deliver($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.delivered')
        );
    }

    /**
     * "Saiu para entrega" — só usado pelas rotas /storefront-orders/*
     * (tela dedicada de gestão de pedidos da loja).
     */
    public function dispatch(Order $order)
    {
        try {
            $order = $this->service->dispatch($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.dispatched')
        );
    }

    /**
     * Desfaz "saiu para entrega" — só usado pelas rotas
     * /storefront-orders/* (tela dedicada de gestão de pedidos da loja).
     */
    public function undispatch(Order $order)
    {
        try {
            $order = $this->service->undispatch($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.undispatched')
        );
    }

    /**
     * Igual a index(), mas força origin=storefront (nunca lido do
     * request) — usado só pela tela dedicada de gestão de pedidos da loja
     * (perm:storefront-orders,read), independente da permissão orders,read.
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
            OrderListResource::collection($list),
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

    public function undeliver(Order $order)
    {
        try {
            $order = $this->service->undeliver($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.undelivered')
        );
    }

    public function pay(PayOrderRequest $request, Order $order)
    {
        $amount = $request->filled('amount') ? (float) $request->input('amount') : null;

        try {
            $order = $this->service->pay($order, $request->input('paid_at'), $amount);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            $order->is_paid ? __('messages.order.paid') : __('messages.order.partially_paid')
        );
    }

    public function unpay(Order $order)
    {
        try {
            $order = $this->service->unpay($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.unpaid')
        );
    }

    public function payInstallment(PayInstallmentRequest $request, Order $order, OrderInstallment $installment)
    {
        try {
            $order = $this->service->payInstallment($order, $installment, $request->input('paid_at'));
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.installment_paid')
        );
    }

    public function unpayInstallment(Order $order, OrderInstallment $installment)
    {
        try {
            $order = $this->service->unpayInstallment($order, $installment);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.installment_unpaid')
        );
    }

    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $dto = CancelOrderDTO::fromArray($request->validated());

        try {
            $order = $this->service->cancel($order, $dto);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.cancelled')
        );
    }

    public function approve(Order $order)
    {
        try {
            $order = $this->service->approve($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.approved')
        );
    }

    /**
     * Gera um link temporário de preparo (roadmap Loja) — token curto que
     * expira em 15 min, aberto sem login pelo celular via QR code. Só a
     * tela de gestão de pedidos da loja usa (perm:storefront-orders,read).
     * Devolve o token cru (o único momento em que ele existe em claro pro
     * frontend montar a URL do QR).
     */
    public function prepLink(Order $order)
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

    public function reject(RejectOrderRequest $request, Order $order)
    {
        try {
            $order = $this->service->reject($order, $request->input('reason'));
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.rejected')
        );
    }

    public function approveCancellation(Order $order)
    {
        try {
            $order = $this->service->approveCancellationRequest($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (InvalidStockMovementException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_STOCK_MOVEMENT');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.cancellation_approved')
        );
    }

    public function rejectCancellation(Order $order)
    {
        try {
            $order = $this->service->rejectCancellationRequest($order);
        } catch (InvalidOrderStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_ORDER_STATE');
        }

        $order->load(self::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.cancellation_rejected')
        );
    }
}
