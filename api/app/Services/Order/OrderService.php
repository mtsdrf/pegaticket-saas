<?php

namespace App\Services\Order;

use App\DTOs\Order\CancelOrderDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Order\UpdateOrderItemsDTO;
use App\Events\Order\OrderApproved;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderCreated;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderInstallmentPaid;
use App\Events\Order\OrderInstallmentUnpaid;
use App\Events\Order\OrderItemsUpdated;
use App\Events\Order\OrderOutForDelivery;
use App\Events\Order\OrderPaid;
use App\Events\Order\OrderPartiallyPaid;
use App\Events\Order\OrderRejected;
use App\Events\Order\OrderUndelivered;
use App\Events\Order\OrderUndispatched;
use App\Events\Order\OrderUnpaid;
use App\Events\Order\OrderCancellationRequested;
use App\Events\Order\OrderCancellationApproved;
use App\Events\Order\OrderCancellationRejected;
use App\Exceptions\InvalidOrderStateException;
use App\Models\BaseModel;
use App\Models\Client\Client;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderItemOption;
use App\Models\Order\OrderPrepLink;
use App\Models\Product\Product;
use App\Models\Product\ProductOption;
use App\Models\Stock\StockLocation;
use App\Models\Stock\StockMovement;
use App\Models\Storefront\CashbackEarning;
use App\Models\Storefront\CashbackRedemption;
use App\Models\Storefront\CouponRedemption;
use App\Exceptions\DiscountLimitExceededException;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Permission\PermissionService;
use App\Services\Product\ProductPricingService;
use App\Services\Product\ProductService;
use App\Services\Stock\StockService;
use App\Services\Workflow\WorkflowTransitionLogger;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo mais complexo do projeto: Pedido, integrado com Estoque desde a
 * criação (reserva por item) e com cascata de quitação (última parcela
 * paga marca o pedido inteiro como pago+entregue). Ver
 * `.claude/memory/architecture-decisions.md` para o desenho completo.
 */
class OrderService
{
    public const EAGER_RELATIONS = ['client.endereco.cidade', 'client.endereco.bairro', 'stockLocation', 'items.product', 'items.options.productOption.group', 'installments', 'coupon', 'rating'];
    public const LIST_EAGER_RELATIONS = ['client'];

    public function __construct(
        private OrderRepositoryInterface $repository,
        private StockService $stockService,
        private ParcelaVencimentoCalculator $vencimentoCalculator,
        private ProductPricingService $pricingService,
        private OrderPaymentService $paymentService,
        private PermissionService $permissionService,
        private WorkflowTransitionLogger $workflowTransitionLogger,
    ) {
    }

    public function find(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return $order;
    }

    /**
     * Link temporário de preparo (roadmap Loja) — token curto (48 chars
     * aleatórios) que expira em 15 minutos, aberto sem login pelo celular
     * via QR code. Não apaga links antigos: cada geração é independente e
     * expira sozinha (podem coexistir).
     */
    public function generatePrepLink(Order $order): OrderPrepLink
    {
        $this->assertBelongsToCurrentTenant($order);

        return OrderPrepLink::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'token' => Str::random(48),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function paginate(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'client_name' => 'clients.name',
            'total_amount' => 'orders.total_amount',
            'is_installment' => 'orders.is_installment',
            'is_paid' => 'orders.is_paid',
            'is_delivered' => 'orders.is_delivered',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $needsClientJoin = $sortColumn === 'clients.name';

        $query = Order::query()
            ->select([
                'orders.id',
                'orders.uuid',
                'orders.client_id',
                'orders.is_installment',
                'orders.total_amount',
                'orders.is_paid',
                'orders.paid_at',
                'orders.is_delivered',
                'orders.delivered_at',
                'orders.due_date',
                'orders.cancelled_at',
                'orders.status',
                'orders.origin',
                'orders.is_out_for_delivery',
                'orders.out_for_delivery_at',
                'orders.created_at',
            ])
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->with(self::LIST_EAGER_RELATIONS);

        if ($needsClientJoin) {
            $query->leftJoin('clients', 'clients.id', '=', 'orders.client_id');
        }

        if (!empty($filters['client_name'])) {
            $query->whereHas('client', fn($q) => $q->where('name', 'like', '%' . $filters['client_name'] . '%'));
        }

        if (!empty($filters['client_uuid'])) {
            $query->whereHas('client', fn($q) => $q->where('uuid', $filters['client_uuid']));
        }

        if (isset($filters['total_amount_min']) && $filters['total_amount_min'] !== '') {
            $query->where('orders.total_amount', '>=', (float) $filters['total_amount_min']);
        }

        if (isset($filters['total_amount_max']) && $filters['total_amount_max'] !== '') {
            $query->where('orders.total_amount', '<=', (float) $filters['total_amount_max']);
        }

        if (array_key_exists('is_paid', $filters) && $filters['is_paid'] !== null) {
            $query->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_delivered', $filters) && $filters['is_delivered'] !== null) {
            $query->where('is_delivered', filter_var($filters['is_delivered'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_installment', $filters) && $filters['is_installment'] !== null) {
            $query->where('is_installment', filter_var($filters['is_installment'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_cancelled', $filters) && $filters['is_cancelled'] !== null) {
            filter_var($filters['is_cancelled'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('cancelled_at')
                : $query->whereNull('cancelled_at');
        }

        // Fila de aprovação do staff (roadmap Delivery, Fase 1) — filtro por
        // status (pending_approval|confirmed|rejected), tela dedicada usa
        // status=pending_approval.
        if (!empty($filters['status'])) {
            $query->where('orders.status', $filters['status']);
        }

        // Tela dedicada de gestão de pedidos da loja (storefront-orders,*)
        // força origin=storefront no controller, nunca lido cru do request.
        if (!empty($filters['origin'])) {
            $query->where('orders.origin', $filters['origin']);
        }

        // Recorte operacional canônico da central de operação — mantém a
        // origem do pedido como atributo e expõe o "estágio de trabalho"
        // atual para o frontend sem obrigar a UI a reconstruir isso a partir
        // de múltiplas flags/combinações no cliente.
        if (!empty($filters['stage'])) {
            match ($filters['stage']) {
                'approval' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.status', 'pending_approval'),
                'production' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.status', 'confirmed')
                    ->where('orders.is_out_for_delivery', false)
                    ->where('orders.is_delivered', false),
                'dispatch' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.status', 'confirmed')
                    ->where('orders.is_out_for_delivery', true)
                    ->where('orders.is_delivered', false),
                'financial_pending' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.is_delivered', true)
                    ->where('orders.is_paid', false),
                default => null,
            };
        }

        // "Somente ativos" (grid de gestão de pedidos da loja) — filtro
        // aditivo: exclui cancelado, recusado e pedido concluído (pago E
        // entregue ao mesmo tempo), deixando só o que ainda demanda ação.
        // Quando ligado, ordena por urgência (pendentes de aprovação
        // primeiro, resto por id crescente), ignorando o sort recebido.
        $activeOnly = array_key_exists('active_only', $filters)
            && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN);

        if ($activeOnly) {
            $query->whereNull('cancelled_at')
                ->where('orders.status', '!=', 'rejected')
                ->where(fn($q) => $q->where('is_paid', false)->orWhere('is_delivered', false));

            $query->orderByRaw("(orders.status = 'pending_approval') DESC, orders.id ASC");
        } else {
            $query->orderBy($sortColumn ?? 'orders.id', GridQuery::normalizeSortDir($sortDir));
        }

        return $query->paginate($perPage);
    }

    /**
     * total_amount/unit_price/line_total são sempre calculados aqui a
     * partir do Product.price atual, nunca confiados no request. Itens são
     * resolvidos em cents (inteiros) para a soma/divisão de parcelas bater
     * exatamente, evitando erro de ponto flutuante.
     */
    public function create(CreateOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $client = Client::where('uuid', $dto->clientUuid)
                ->where('tenant_id', $dto->tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $stockLocation = $dto->stockLocationUuid
                ? StockLocation::where('uuid', $dto->stockLocationUuid)
                    ->where('tenant_id', $dto->tenantId)
                    ->whereNull('deleted_at')
                    ->firstOrFail()
                : $this->resolveDefaultStockLocation($dto->tenantId);

            $lines = [];
            $totalCents = 0;

            foreach ($dto->items as $item) {
                $product = Product::where('uuid', $item['product_uuid'])
                    ->where('tenant_id', $dto->tenantId)
                    ->whereNull('deleted_at')
                    ->with(['optionGroups.options'])
                    ->firstOrFail();

                $line = $this->resolveOrderItemLine($product, $client, $item, $dto->tenantId);

                $totalCents += $line['line_total_cents'];
                $lines[] = $line;
            }

            // Taxa de entrega (roadmap Delivery, Fase 2) — somada ao total do
            // pedido em centavos, mesmo cuidado de arredondamento das linhas
            // de item, mas persistida também como coluna própria
            // (delivery_fee) para o cliente ver o breakdown produto x
            // entrega. default 0.0 preserva 100% o fluxo staff existente.
            $deliveryFeeCents = (int) round($dto->deliveryFee * 100);
            $totalCents += $deliveryFeeCents;

            // Taxa de serviço (roadmap Balcão, Fases 1+2) — acréscimo somado
            // ao total, espelhando delivery_fee: persistida em coluna própria
            // (service_fee) para o breakdown, distinta de entrega em
            // relatórios. default 0.0 preserva os fluxos staff/pdv/storefront.
            $serviceFeeCents = (int) round($dto->serviceFee * 100);
            $totalCents += $serviceFeeCents;

            // Desconto de cupom (roadmap Delivery, Fase 3) — subtraído
            // depois da taxa de entrega já somada (mesma ordem do plano:
            // subtotal + entrega - desconto), max(0, ...) defensivo pra
            // nunca persistir total_amount negativo mesmo num cenário
            // inesperado de desconto maior que o total. default 0.0
            // preserva 100% o fluxo staff existente (nunca usa cupom).
            $discountAmountCents = (int) round($dto->discountAmount * 100);
            $totalCents = max(0, $totalCents - $discountAmountCents);

            // Resgate de cashback (roadmap Delivery, Fase 5) — mesma ordem
            // do cupom, subtraído por último. CashbackService já validou e
            // reservou esse valor contra o saldo real do cliente ANTES de
            // chamar create() (StorefrontCheckoutService); aqui só persiste
            // o valor já decidido, congelado no pedido.
            $cashbackRedeemedAmountCents = (int) round($dto->cashbackRedeemedAmount * 100);
            $totalCents = max(0, $totalCents - $cashbackRedeemedAmountCents);

            $dueDate = $dto->isInstallment ? null : $this->vencimentoCalculator->calculateDueDate(now());

            // Sequência de exibição por tenant (tenants.next_order_code) —
            // increment() já é atômico (UPDATE de linha única); a leitura
            // seguinte, na mesma transação, enxerga o próprio valor já
            // incrementado (read-your-own-writes), sem precisar de
            // lockForUpdate() explícito adicional.
            DB::table('tenants')->where('id', $dto->tenantId)->increment('next_order_code');
            $codigo = (string) DB::table('tenants')->where('id', $dto->tenantId)->value('next_order_code');

            $order = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'client_id' => $client->id,
                'stock_location_id' => $stockLocation->id,
                'codigo' => $codigo,
                'is_installment' => $dto->isInstallment,
                'total_amount' => $this->centsToDecimal($totalCents),
                'delivery_fee' => $dto->deliveryFee,
                'service_fee' => $dto->serviceFee,
                'coupon_id' => $dto->couponId,
                'discount_amount' => $dto->discountAmount,
                'cashback_redeemed_amount' => $dto->cashbackRedeemedAmount,
                'is_paid' => false,
                'is_delivered' => false,
                'due_date' => $dueDate,
                'expected_delivery_date' => $dto->expectedDeliveryDate,
                'notes' => $dto->notes,
                'payment_method' => $dto->paymentMethod,
                'needs_change' => $dto->needsChange,
                'change_for_amount' => $dto->changeForAmount,
                'status' => $dto->status,
                'origin' => $dto->origin,
                'stock_reserved' => $dto->reserveStock,
                'fulfillment_type' => $dto->fulfillmentType,
            ]);

            foreach ($lines as $line) {
                $orderItem = OrderItem::create([
                    'tenant_id' => $dto->tenantId,
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->centsToDecimal($line['unit_price_cents']),
                    'line_total' => $this->centsToDecimal($line['line_total_cents']),
                    'notes' => $line['notes'],
                ]);

                $this->syncOrderItemOptions($orderItem, $line['options']);

                if ($dto->reserveStock) {
                    $this->stockService->reserve(
                        product: $line['product'],
                        location: $stockLocation,
                        quantity: $line['quantity'],
                        reason: "Reserva do pedido {$order->uuid}",
                        sourceType: OrderItem::class,
                        sourceId: $orderItem->id,
                    );
                }
            }

            if ($dto->isInstallment) {
                $this->createInstallments($order, $dto->installmentsCount, $totalCents);
            }

            event(new OrderCreated(
                orderId: $order->id,
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            // Ecoam os defaults do formulário legado ("entregue"/"pago" já
            // marcados na criação) sem reabrir update genérico — reaproveitam
            // exatamente a mesma lógica interna de deliver()/pay() (sem
            // guard/lock próprios: já estamos dentro da transação de
            // criação, sobre uma linha ainda não visível a outras
            // transações). mark_as_paid=true com is_installment=true já é
            // bloqueado na validação (StoreOrderRequest), guard aqui é só
            // defensivo.
            if ($dto->markAsDelivered) {
                $order = $this->performDelivery($order);
            }

            if ($dto->markAsPaid && !$dto->isInstallment) {
                $order = $this->performPayment($order);
            }

            return $order;
        });
    }

    /**
     * Edição de itens/cabeçalho de um pedido já criado — escopo
     * deliberadamente limitado (não reabre client_uuid/is_installment,
     * ver architecture-decisions.md), só permitida enquanto o pedido não
     * está entregue/pago/cancelado. Estruturalmente análogo a
     * OrderInstallmentService::reallocate() (diff do array inteiro contra
     * o estado atual via uuid), com a diferença de que cada item também
     * mexe em reserva de estoque (StockService), não só valor.
     *
     * Pedido parcelado: total_amount muda mas as installments NÃO são
     * recalculadas automaticamente aqui (decisão intencional — usuário
     * ajusta via PUT /orders/{order}/installments depois).
     */
    public function updateItems(Order $order, UpdateOrderItemsDTO $dto): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $dto) {
            $order = $this->lockOrder($order);

            $this->assertOrderItemsEditable($order);

            $order->loadMissing('client', 'stockLocation');
            $client = $order->client;

            $stockLocation = $dto->stockLocationUuid
                ? StockLocation::where('uuid', $dto->stockLocationUuid)
                    ->where('tenant_id', $order->tenant_id)
                    ->whereNull('deleted_at')
                    ->firstOrFail()
                : $order->stockLocation;

            $currentItems = OrderItem::where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('uuid');

            // Validação do payload inteiro ANTES de mutar qualquer coisa
            // — mesmo cuidado "tudo ou nada" de OrderInstallmentService::reallocate().
            $seenUuids = [];

            foreach ($dto->items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid === null) {
                    continue;
                }

                if (in_array($uuid, $seenUuids, true)) {
                    throw new InvalidOrderStateException(__('messages.order.item_duplicate_reference'));
                }
                $seenUuids[] = $uuid;

                if (!$currentItems->has($uuid)) {
                    throw new InvalidOrderStateException(__('messages.order.item_not_found_in_order'));
                }
            }

            // Itens novos (sem uuid): cria + reserva, igual a create().
            foreach ($dto->items as $item) {
                if (($item['uuid'] ?? null) !== null) {
                    continue;
                }

                $product = Product::where('uuid', $item['product_uuid'])
                    ->where('tenant_id', $order->tenant_id)
                    ->whereNull('deleted_at')
                    ->with(['optionGroups.options'])
                    ->firstOrFail();
                $line = $this->resolveOrderItemLine($product, $client, $item, $order->tenant_id);

                $newItem = OrderItem::create([
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->centsToDecimal($line['unit_price_cents']),
                    'line_total' => $this->centsToDecimal($line['line_total_cents']),
                    'notes' => $line['notes'],
                ]);

                $this->syncOrderItemOptions($newItem, $line['options']);

                if ($order->stock_reserved) {
                    $this->stockService->reserve(
                        product: $product,
                        location: $stockLocation,
                        quantity: $line['quantity'],
                        reason: "Atualização de itens do pedido {$order->uuid}",
                        sourceType: OrderItem::class,
                        sourceId: $newItem->id,
                    );
                }
            }

            // Itens existentes referenciados no payload (com uuid): atualiza.
            foreach ($dto->items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid === null) {
                    continue;
                }

                $existing = $currentItems->get($uuid);
                $existing->loadMissing('options.productOption.group');

                $product = Product::where('uuid', $item['product_uuid'])
                    ->where('tenant_id', $order->tenant_id)
                    ->whereNull('deleted_at')
                    ->with(['optionGroups.options'])
                    ->firstOrFail();

                $line = $this->resolveOrderItemLine($product, $client, $item, $order->tenant_id, $existing);
                $quantity = $line['quantity'];
                $productChanged = (int) $existing->product_id !== (int) $product->id;
                $quantityChanged = abs(round((float) $existing->quantity, 3) - round($quantity, 3)) > 0.0001;
                $optionsChanged = $this->orderItemOptionsChanged($existing, $line['options']);

                if (($productChanged || $quantityChanged) && $order->stock_reserved) {
                    $reserveMovement = $this->findReserveMovement($order, $existing);

                    $this->stockService->reserveCancel(
                        originalReserve: $reserveMovement,
                        notes: "Atualização de itens do pedido {$order->uuid}",
                    );

                    $this->stockService->reserve(
                        product: $product,
                        location: $stockLocation,
                        quantity: $quantity,
                        reason: "Atualização de itens do pedido {$order->uuid}",
                        sourceType: OrderItem::class,
                        sourceId: $existing->id,
                    );
                }

                $existing->product_id = $product->id;
                $existing->quantity = $quantity;
                $existing->unit_price = $this->centsToDecimal($line['unit_price_cents']);
                $existing->line_total = $this->centsToDecimal($line['line_total_cents']);
                $existing->notes = $line['notes'];
                $existing->save();

                if ($productChanged || $quantityChanged || $optionsChanged) {
                    $this->syncOrderItemOptions($existing, $line['options']);
                }
            }

            // Itens existentes NÃO referenciados no payload: remove.
            foreach ($currentItems as $uuid => $existing) {
                if (in_array($uuid, $seenUuids, true)) {
                    continue;
                }

                if ($order->stock_reserved) {
                    $reserveMovement = $this->findReserveMovement($order, $existing);

                    $this->stockService->reserveCancel(
                        originalReserve: $reserveMovement,
                        notes: "Atualização de itens do pedido {$order->uuid}",
                    );
                }

                $existing->delete();
            }

            $totalCents = (int) round(
                (float) OrderItem::where('order_id', $order->id)->whereNull('deleted_at')->sum('line_total') * 100
            );

            $order->total_amount = $this->centsToDecimal($totalCents);

            if ($dto->notes !== null) {
                $order->notes = $dto->notes;
            }

            if ($dto->stockLocationUuid) {
                $order->stock_location_id = $stockLocation->id;
            }

            if ($dto->expectedDeliveryDate !== null) {
                $order->expected_delivery_date = $dto->expectedDeliveryDate;
            }

            $order->save();

            event(new OrderItemsUpdated(
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            return $order->fresh()->load(self::EAGER_RELATIONS);
        });
    }

    public function deliver(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if ($order->is_delivered) {
                throw new InvalidOrderStateException(__('messages.order.already_delivered'));
            }

            return $this->performDelivery($order);
        });
    }

    /**
     * "Saiu para entrega" (tela dedicada de gestão de pedidos da loja) —
     * etapa intermediária opcional entre aprovado e entregue, só faz
     * sentido pra pedido já aprovado (reaproveita assertOrderIsApproved()).
     * Não exige dispatch() antes de deliver() — pedido origin='staff' (sem
     * conceito de "saiu para entrega") continua indo direto pra entregue.
     */
    public function dispatch(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if ($order->is_delivered) {
                throw new InvalidOrderStateException(__('messages.order.already_delivered'));
            }

            if ($order->is_out_for_delivery) {
                throw new InvalidOrderStateException(__('messages.order.already_out_for_delivery'));
            }

            $order->is_out_for_delivery = true;
            $order->out_for_delivery_at = now();
            $order->save();

            event(new OrderOutForDelivery(
                orderId: $order->id,
                orderUuid: $order->uuid,
                fromStage: 'production',
                toStage: 'dispatch',
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Desfaz dispatch(): volta o pedido de "saiu para entrega" para o
     * estado aprovado/confirmado. Só faz sentido para pedido ainda não
     * entregue (undeliver() é quem desfaz a entrega). Pedido continua
     * ativo — não mexe em estoque (dispatch() também não mexeu). Mesmo
     * padrão de transação com lock de dispatch()/undeliver().
     */
    public function undispatch(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if (!$order->is_out_for_delivery) {
                throw new InvalidOrderStateException(__('messages.order.not_out_for_delivery'));
            }

            if ($order->is_delivered) {
                throw new InvalidOrderStateException(__('messages.order.already_delivered'));
            }

            $order->is_out_for_delivery = false;
            $order->out_for_delivery_at = null;
            $order->save();

            event(new OrderUndispatched(
                orderId: $order->id,
                orderUuid: $order->uuid,
                fromStage: 'dispatch',
                toStage: 'production',
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Pedido de origem storefront (Fase 1) nasce `pending_approval` e só
     * pode ser entregue/pago depois que o staff aprova (status vira
     * `confirmed`) — sem essa checagem, deliver()/pay()/payInstallment()
     * contornam a fila de aprovação por completo (achado de code review:
     * um pedido nunca aprovado, ou explicitamente `rejected`, podia ser
     * entregue/pago normalmente, causando inclusive saída física de
     * estoque para um pedido `stock_reserved=false` nunca aprovado).
     */
    private function assertOrderIsApproved(Order $order): void
    {
        if ($order->status === 'pending_approval') {
            throw new InvalidOrderStateException(__('messages.order.awaiting_approval'));
        }

        if ($order->status === 'rejected') {
            throw new InvalidOrderStateException(__('messages.order.order_rejected'));
        }
    }

    /**
     * Desfaz deliver(): devolve o estoque convertido em saída de volta
     * para RESERVADO (não só "disponível") — ver performUndelivery() para
     * o porquê dos dois passos por item. Pedido continua ativo (diferente
     * de cancel(), que é definitivo).
     */
    public function undeliver(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if (!$order->is_delivered) {
                throw new InvalidOrderStateException(__('messages.order.not_delivered'));
            }

            return $this->performUndelivery($order);
        });
    }

    /**
     * Só para pedido não parcelado — pagamento é sempre integral aqui,
     * SALVO quando `$amount` informa um valor parcial (paridade com o
     * legado: campo `valor_pago` independente do booleano de quitação
     * total). Pedido parcelado usa payInstallment() por parcela (não
     * aceita pagamento parcial de parcela, só a parcela inteira).
     *
     * Regras de `$amount`:
     * - null, <= 0 ou >= total_amount: pagamento total normal (mesmo
     *   comportamento de sempre, `is_paid` vira true).
     * - > 0 e < total_amount: pagamento PARCIAL — só grava `paid_amount`
     *   (valor absoluto já pago até agora, substitui o anterior, não
     *   soma), sem tocar `is_paid`/`paid_at` nem disparar cascata de
     *   entrega. Dispara OrderPartiallyPaid em vez de OrderPaid.
     */
    public function pay(Order $order, ?string $paidAt = null, ?float $amount = null): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        if ($order->is_installment) {
            throw new InvalidOrderStateException(__('messages.order.use_installment_pay'));
        }

        return DB::transaction(function () use ($order, $paidAt, $amount) {
            $order = $this->lockOrder($order);

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if ($order->is_paid) {
                throw new InvalidOrderStateException(__('messages.order.already_paid'));
            }

            $isPartialPayment = $amount !== null && $amount > 0 && $amount < (float) $order->total_amount;

            if ($isPartialPayment) {
                return $this->performPartialPayment($order, $amount);
            }

            return $this->performPayment($order, $paidAt ? \Carbon\Carbon::parse($paidAt) : null);
        });
    }

    /**
     * Desfaz pay(). Só para pedido não parcelado — pedido parcelado usa
     * unpayInstallment() por parcela.
     */
    public function unpay(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        if ($order->is_installment) {
            throw new InvalidOrderStateException(__('messages.order.use_installment_unpay'));
        }

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if (!$order->is_paid) {
                throw new InvalidOrderStateException(__('messages.order.not_paid'));
            }

            return $this->performUnpayment($order);
        });
    }

    /**
     * Cascata de quitação (confirmada no legado): ao pagar a última
     * parcela pendente, o pedido inteiro vira is_paid=true E
     * is_delivered=true — se ainda não tinha sido entregue, a mesma
     * integração de estoque de deliver() roda aqui dentro da transação.
     */
    public function payInstallment(Order $order, OrderInstallment $installment, ?string $paidAt = null): Order
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        if (!$order->is_installment) {
            throw new InvalidOrderStateException(__('messages.order.not_installment'));
        }

        return DB::transaction(function () use ($order, $installment, $paidAt) {
            $order = $this->lockOrder($order);
            $installment = OrderInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if ($installment->is_paid) {
                throw new InvalidOrderStateException(__('messages.order.installment_already_paid'));
            }

            $installment->is_paid = true;
            $installment->paid_at = $paidAt ? \Carbon\Carbon::parse($paidAt) : now();
            $installment->save();

            event(new OrderInstallmentPaid(
                orderUuid: $order->uuid,
                installmentUuid: $installment->uuid,
                actorId: Auth::id()
            ));

            $allInstallmentsPaid = OrderInstallment::where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->where('is_paid', false)
                ->doesntExist();

            if ($allInstallmentsPaid) {
                if (!$order->is_delivered) {
                    $order = $this->performDelivery($order);
                }

                $order = $this->performPayment($order, $paidAt ? \Carbon\Carbon::parse($paidAt) : null);
            }

            return $order->fresh();
        });
    }

    /**
     * Desfaz payInstallment() de UMA parcela. Reversão de cascata: se o
     * pedido tinha virado is_paid=true (só acontece quando TODAS as
     * parcelas estavam pagas), volta para false já que agora nem todas
     * estão. NÃO mexe em is_delivered/delivered_at — entrega é um fato
     * físico independente de pagamento; desfazer o pagamento de uma
     * parcela não desfaz uma entrega que já aconteceu (decisão de
     * design, ver .claude/memory/api-patterns.md).
     */
    public function unpayInstallment(Order $order, OrderInstallment $installment): Order
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        if (!$order->is_installment) {
            throw new InvalidOrderStateException(__('messages.order.not_installment'));
        }

        return DB::transaction(function () use ($order, $installment) {
            $order = $this->lockOrder($order);
            $installment = OrderInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            if (!$installment->is_paid) {
                throw new InvalidOrderStateException(__('messages.order.installment_not_paid'));
            }

            $installment->is_paid = false;
            $installment->paid_at = null;
            $installment->save();

            event(new OrderInstallmentUnpaid(
                orderUuid: $order->uuid,
                installmentUuid: $installment->uuid,
                actorId: Auth::id()
            ));

            if ($order->is_paid) {
                $order = $this->performUnpayment($order);
            }

            return $order->fresh();
        });
    }

    /**
     * Pedido inteiro, não item a item. Bloqueado se qualquer parcela (ou
     * o pedido não parcelado) já estiver paga. Efeito no estoque depende
     * de já ter sido entregue (returnStock) ou não (reserveCancel).
     */
    public function cancel(Order $order, CancelOrderDTO $dto): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $dto) {
            $order = $this->lockOrder($order);
            $fromStage = $this->workflowTransitionLogger->resolveOrderStage($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
            }

            $hasPaidAmount = $order->is_installment
                ? OrderInstallment::where('order_id', $order->id)
                    ->whereNull('deleted_at')
                    ->where('is_paid', true)
                    ->exists()
                : $order->is_paid;

            // Pagamento via Pix (registro em `payments`, roadmap 2A) permite
            // cancelar gerando um Refund pendente — o dinheiro já entrou, não
            // pode ser simplesmente "desmarcado". Pagamento manual legado
            // (is_paid/parcela paga SEM registro em `payments`) continua
            // bloqueado: exige estorno manual fora do sistema.
            $paidPixCharge = $hasPaidAmount ? $this->paymentService->paidChargeForOrder($order) : null;

            if ($hasPaidAmount && $paidPixCharge === null) {
                throw new InvalidOrderStateException(__('messages.order.cannot_cancel_paid'));
            }

            // select() exclui image_data — ver convertReservationsToExit().
            $order->loadMissing([
                'items.product' => fn($query) => $query->select(ProductService::listColumns()),
                'stockLocation',
            ]);

            // Saída física (entrega) já aconteceu de forma incondicional em
            // performDelivery() (ver convertReservationsToExit()), mesmo
            // para pedido com stock_reserved=false — por isso a devolução
            // aqui também é incondicional. Só a liberação de RESERVA (pedido
            // ainda não entregue) depende de reserva ter existido.
            foreach ($order->items as $item) {
                if ($order->is_delivered) {
                    $this->stockService->returnStock(
                        product: $item->product,
                        location: $order->stockLocation,
                        quantity: (float) $item->quantity,
                        reason: "Cancelamento do pedido {$order->uuid}",
                    );

                    continue;
                }

                if (!$order->stock_reserved) {
                    continue;
                }

                $reserveMovement = $this->findReserveMovement($order, $item);

                $this->stockService->reserveCancel(
                    originalReserve: $reserveMovement,
                    notes: "Cancelamento do pedido {$order->uuid}",
                );
            }

            $order->cancelled_at = now();
            $order->cancellation_reason = $dto->cancellationReason;
            $order->save();

            // Pedido pago via Pix sendo cancelado: gera um Refund pendente
            // (não apaga o pagamento) — roadmap 2A.
            if ($paidPixCharge !== null) {
                $this->paymentService->createRefundForPaidCancellation($order);
            }

            event(new OrderCancelled(
                orderId: $order->id,
                orderUuid: $order->uuid,
                fromStage: $fromStage,
                toStage: 'cancelled',
                cancellationReason: $dto->cancellationReason,
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Fila de aprovação do staff (roadmap Delivery, Fase 1) — todo pedido
     * nascido da loja (origin='storefront') nasce status='pending_approval'
     * e precisa passar por aqui antes de virar 'confirmed'. Pedido
     * origin='staff' já nasce 'confirmed' (default do DTO), nunca passa por
     * este método no fluxo normal.
     */
    public function approve(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'pending_approval') {
                throw new InvalidOrderStateException(__('messages.order.not_pending_approval'));
            }

            $order->status = 'confirmed';
            $order->save();

            event(new OrderApproved(
                orderId: $order->id,
                orderUuid: $order->uuid,
                fromStage: 'approval',
                toStage: $this->workflowTransitionLogger->resolveOrderStage($order),
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Recusa um pedido pendente de aprovação. Se houve reserva de estoque
     * na criação (stock_reserved=true), libera por item — mesmo formato de
     * liberação de reserva já usado em cancel() pro caminho "ainda não
     * entregue" (pedido pending_approval nunca chega a ser entregue antes
     * de passar por aqui). NÃO seta cancelled_at (fica null) — a distinção
     * "recusado" fica só no campo status, pra não colidir com relatórios
     * que hoje derivam "foi cancelado" de cancelled_at !== null.
     */
    public function reject(Order $order, ?string $reason): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $reason) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'pending_approval') {
                throw new InvalidOrderStateException(__('messages.order.not_pending_approval'));
            }

            if ($order->stock_reserved) {
                // select() exclui image_data — ver convertReservationsToExit().
                $order->loadMissing([
                    'items.product' => fn($query) => $query->select(ProductService::listColumns()),
                    'stockLocation',
                ]);

                foreach ($order->items as $item) {
                    $reserveMovement = $this->findReserveMovement($order, $item);

                    $this->stockService->reserveCancel(
                        originalReserve: $reserveMovement,
                        notes: "Recusa do pedido {$order->uuid}",
                    );
                }
            }

            // Pedido recusado nunca deve consumir o limite de uso do
            // cliente sobre um cupom (mesmo espírito de liberar a reserva
            // de estoque acima) — roadmap Delivery, Fase 3.
            if ($order->coupon_id !== null) {
                CouponRedemption::where('order_id', $order->id)->delete();
            }

            // Resgate de cashback (roadmap Delivery, Fase 5) — mesmo
            // espírito do bloco de cupom acima: pedido recusado nunca deve
            // consumir saldo do cliente. Devolve o valor drenado de cada
            // lote de origem (pode ter tocado mais de um, FIFO por
            // expires_at) antes de apagar as linhas de resgate.
            if ((float) $order->cashback_redeemed_amount > 0) {
                $redemptions = CashbackRedemption::where('order_id', $order->id)->get();

                foreach ($redemptions as $redemption) {
                    CashbackEarning::where('id', $redemption->cashback_earning_id)
                        ->increment('remaining_amount', $redemption->amount);
                }

                CashbackRedemption::where('order_id', $order->id)->delete();
            }

            $order->status = 'rejected';
            $order->cancellation_reason = $reason;
            $order->save();

            event(new OrderRejected(
                orderId: $order->id,
                orderUuid: $order->uuid,
                fromStage: 'approval',
                toStage: 'rejected',
                reason: $reason,
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Solicitação de cancelamento pelo CLIENTE FINAL via Portal (roadmap
     * A4 — "aprovar cancelamento"). Chamado sem tenant resolvido
     * (rotas /portal/* não têm middleware `tenant`) — de propósito, SEM
     * assertBelongsToCurrentTenant(): a posse do pedido já foi validada
     * antes de chegar aqui via
     * PortalCustomerService::findOwnedOrder() (pedido pertence a um
     * Client vinculado ao FinalCustomer via link confirmado). Não muda
     * estoque/pagamento ainda — só marca a intenção; cancel() de verdade
     * só roda em approveCancellationRequest(), quando o staff aprova.
     */
    public function requestCancellation(Order $order, ?string $reason, ?string $finalCustomerUuid = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $finalCustomerUuid) {
            $order = $this->lockOrder($order);

            $this->assertCancellationRequestEligible($order);

            $order->status_before_cancellation_request = $order->status;
            $order->status = 'cancellation_requested';
            $order->cancellation_reason = $reason;
            $order->save();

            event(new OrderCancellationRequested(
                orderUuid: $order->uuid,
                reason: $reason,
                finalCustomerUuid: $finalCustomerUuid,
            ));

            return $order;
        });
    }

    /**
     * Só o cliente final pode solicitar (decisão de produto), e só
     * enquanto o pedido não passou de "saiu para entrega"/"entregue" —
     * ambos representados por flags (is_out_for_delivery/is_delivered),
     * não pelo enum de status. Pedido já `rejected`/já cancelado/já com
     * solicitação em aberto também bloqueiam (nada a cancelar/duplicar).
     */
    private function assertCancellationRequestEligible(Order $order): void
    {
        if ($order->origin !== 'storefront') {
            throw new InvalidOrderStateException(__('messages.order.cancellation_request_not_storefront'));
        }

        if ($order->cancelled_at !== null) {
            throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
        }

        if ($order->status === 'cancellation_requested') {
            throw new InvalidOrderStateException(__('messages.order.cancellation_already_requested'));
        }

        if ($order->status === 'rejected') {
            throw new InvalidOrderStateException(__('messages.order.order_rejected'));
        }

        if ($order->is_out_for_delivery) {
            throw new InvalidOrderStateException(__('messages.order.already_out_for_delivery'));
        }

        if ($order->is_delivered) {
            throw new InvalidOrderStateException(__('messages.order.already_delivered'));
        }
    }

    /**
     * Staff aprova a solicitação de cancelamento do cliente final —
     * reaproveita cancel() por inteiro (mesma liberação de
     * estoque/estorno, nada duplicado), só que disparada depois da
     * aprovação em vez de imediatamente. status é revertido para o valor
     * anterior à solicitação (status nunca representa "cancelado" — quem
     * representa isso é cancelled_at, mesmo invariante já usado em
     * cancel()/reject()).
     */
    public function approveCancellationRequest(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'cancellation_requested') {
                throw new InvalidOrderStateException(__('messages.order.no_cancellation_requested'));
            }

            $previousStatus = $order->status_before_cancellation_request ?? 'confirmed';
            $reason = $order->cancellation_reason ?: __('messages.order.cancellation_requested_by_client_default');

            $order = $this->cancel($order, new CancelOrderDTO(cancellationReason: $reason));

            $order->status = $previousStatus;
            $order->status_before_cancellation_request = null;
            $order->save();

            event(new OrderCancellationApproved(
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Staff rejeita a solicitação — reverte para o status anterior e
     * limpa o motivo, sem tocar estoque/pagamento (nada chegou a ser
     * executado, cancel() nunca rodou).
     */
    public function rejectCancellationRequest(Order $order): Order
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'cancellation_requested') {
                throw new InvalidOrderStateException(__('messages.order.no_cancellation_requested'));
            }

            $order->status = $order->status_before_cancellation_request ?? 'confirmed';
            $order->status_before_cancellation_request = null;
            $order->cancellation_reason = null;
            $order->save();

            event(new OrderCancellationRejected(
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Converte reserva em saída real de estoque e marca o pedido como
     * entregue. Sem guard/lock/transação próprios — quem chama já garantiu
     * a validade da transição (deliver() valida cancelled_at/is_delivered
     * e trava a linha; create() está criando a linha agora, sem
     * concorrência possível; a cascata de payInstallment() já trava a
     * linha e checa is_delivered antes de chamar). Reaproveitado nos 3
     * lugares para não duplicar a integração com Estoque.
     */
    private function performDelivery(Order $order): Order
    {
        $this->convertReservationsToExit($order);

        $fromStage = $order->is_out_for_delivery ? 'dispatch' : 'production';
        $order->is_delivered = true;
        $order->delivered_at = now();
        $order->save();

        event(new OrderDelivered(
            orderId: $order->id,
            orderUuid: $order->uuid,
            fromStage: $fromStage,
            toStage: $this->workflowTransitionLogger->resolveOrderStage($order),
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * Inverso de performDelivery(): reverte a saída real de volta para
     * reserva (ver revertReservationsFromExit() para o porquê dos dois
     * passos por item) e desmarca o pedido como entregue. Sem
     * guard/lock/transação próprios — mesma convenção de performDelivery().
     */
    private function performUndelivery(Order $order): Order
    {
        $this->revertReservationsFromExit($order);

        $order->is_delivered = false;
        $order->delivered_at = null;
        $order->save();

        event(new OrderUndelivered(
            orderUuid: $order->uuid,
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * Marca o pedido como pago integralmente. Sem guard/lock próprios —
     * mesma lógica de performDelivery() acima. Reaproveitado por pay(),
     * create() (mark_as_paid) e pela cascata de payInstallment().
     */
    private function performPayment(Order $order, ?\Carbon\CarbonInterface $paidAt = null): Order
    {
        $order->is_paid = true;
        $order->paid_at = $paidAt ?? now();
        // Pago total sempre implica paid_amount = total_amount, mesmo
        // quando chegou aqui via um pay() com $amount >= total_amount ou
        // via a cascata de payInstallment() — mantém a mesma invariante
        // do pagamento parcial (paid_amount nunca "sobra" desatualizado).
        $order->paid_amount = $order->total_amount;
        $order->save();

        event(new OrderPaid(
            orderUuid: $order->uuid,
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * Inverso de performPayment(). Reaproveitado por unpay() e pela
     * reversão de cascata de unpayInstallment(). Zera paid_amount —
     * desfazer o pagamento reverte completamente, não preserva um valor
     * parcial anterior a ele.
     */
    private function performUnpayment(Order $order): Order
    {
        $order->is_paid = false;
        $order->paid_at = null;
        $order->paid_amount = null;
        $order->save();

        event(new OrderUnpaid(
            orderUuid: $order->uuid,
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * Pagamento PARCIAL (paridade com o legado `valor_pago`): grava só o
     * valor absoluto já pago, sem tocar is_paid/paid_at nem disparar a
     * cascata de entrega de performPayment(). paid_amount é substituído
     * (não somado) — o valor informado já é o total acumulado até agora,
     * do jeito que o legado grava.
     */
    private function performPartialPayment(Order $order, float $amount): Order
    {
        $order->paid_amount = $this->centsToDecimal((int) round($amount * 100));
        $order->save();

        event(new OrderPartiallyPaid(
            orderUuid: $order->uuid,
            amount: $order->paid_amount,
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * @param int $totalCents Soma exata dos order_items em centavos.
     * Divisão igual entre as N parcelas, última parcela absorve o resto
     * do arredondamento para a soma bater exatamente com total_amount.
     */
    private function createInstallments(Order $order, int $installmentsCount, int $totalCents): void
    {
        $baseCents = intdiv($totalCents, $installmentsCount);
        $remainderCents = $totalCents - ($baseCents * $installmentsCount);

        for ($number = 1; $number <= $installmentsCount; $number++) {
            $amountCents = $baseCents + ($number === $installmentsCount ? $remainderCents : 0);

            $dueDate = $this->vencimentoCalculator->calculateDueDate(now()->addMonthsNoOverflow($number - 1));

            OrderInstallment::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'installment_number' => $number,
                'amount' => $this->centsToDecimal($amountCents),
                'due_date' => $dueDate,
                'is_paid' => false,
            ]);
        }
    }

    /**
     * Para cada item: cancela a reserva original e registra a saída real
     * — dois movimentos, dois registros no ledger. Reaproveitado tanto
     * por deliver() quanto pela cascata de quitação em payInstallment().
     */
    private function convertReservationsToExit(Order $order): void
    {
        // select() exclui image_data (BLOB, ~1MB/produto) — sem isso, um
        // pedido com vários itens de produtos com foto grande pode estourar
        // memory_limit só pra registrar a saída de estoque, que nunca usa a
        // imagem. Mesmo padrão de whitelist de ProductService::listColumns().
        $order->loadMissing([
            'items.product' => fn($query) => $query->select(ProductService::listColumns()),
            'stockLocation',
        ]);

        foreach ($order->items as $item) {
            if ($order->stock_reserved) {
                $reserveMovement = $this->findReserveMovement($order, $item);

                $this->stockService->reserveCancel(
                    originalReserve: $reserveMovement,
                    notes: "Entrega do pedido {$order->uuid}",
                );
            }

            $this->stockService->exit(
                product: $item->product,
                location: $order->stockLocation,
                quantity: (float) $item->quantity,
                reason: "Entrega do pedido {$order->uuid}",
            );
        }
    }

    /**
     * Inverso de convertReservationsToExit(): para cada item, credita de
     * volta o saldo disponível (returnStock(), mesma chamada que cancel()
     * já faz para pedido entregue) e IMEDIATAMENTE recria a reserva
     * (reserve(), mesma chamada que create() faz na criação do pedido) —
     * sem o segundo passo, o saldo voltaria só para "disponível" em vez
     * de "reservado", e um deliver() seguinte quebraria em
     * findReserveMovement() (nenhum StockMovement tipo 'reserve' ativo
     * apontando para este OrderItem). Reaproveitado só por
     * performUndelivery().
     */
    private function revertReservationsFromExit(Order $order): void
    {
        // Mesmo motivo do select() em convertReservationsToExit() — evitar
        // carregar image_data (BLOB) à toa.
        $order->loadMissing([
            'items.product' => fn($query) => $query->select(ProductService::listColumns()),
            'stockLocation',
        ]);

        foreach ($order->items as $item) {
            $this->stockService->returnStock(
                product: $item->product,
                location: $order->stockLocation,
                quantity: (float) $item->quantity,
                reason: "Reversão de entrega do pedido {$order->uuid}",
            );

            if ($order->stock_reserved) {
                $this->stockService->reserve(
                    product: $item->product,
                    location: $order->stockLocation,
                    quantity: (float) $item->quantity,
                    reason: "Reversão de entrega do pedido {$order->uuid}",
                    sourceType: OrderItem::class,
                    sourceId: $item->id,
                );
            }
        }
    }

    /**
     * Busca a reserva ATIVA (ainda não cancelada) de um item — não basta
     * "a primeira reserve encontrada", porque undeliver() pode recriar
     * uma nova reserva para o mesmo OrderItem depois que a original já
     * foi cancelada (convertida em saída na entrega). Sem o filtro
     * whereNotExists, um SEGUNDO deliver()/cancel() depois de undeliver()
     * podia reencontrar a reserva ORIGINAL (já cancelada) em vez da nova,
     * e reserveCancel() rejeitaria com "reserva já cancelada" — achado
     * durante a implementação de undeliver() (2026-07-14), documentado em
     * .claude/memory/api-patterns.md.
     */
    private function findReserveMovement(Order $order, OrderItem $item): StockMovement
    {
        $movement = StockMovement::where('tenant_id', $order->tenant_id)
            ->where('source_type', OrderItem::class)
            ->where('source_id', $item->id)
            ->where('type', 'reserve')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_movements as reserve_cancels')
                    ->whereColumn('reserve_cancels.source_id', 'stock_movements.id')
                    ->where('reserve_cancels.source_type', StockMovement::class)
                    ->where('reserve_cancels.type', 'reserve_cancel')
                    ->whereNull('reserve_cancels.deleted_at');
            })
            ->orderByDesc('id')
            ->first();

        if (!$movement) {
            throw new InvalidOrderStateException(__('messages.order.missing_reservation'));
        }

        return $movement;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *   product: Product,
     *   quantity: float,
     *   unit_price_cents: int,
     *   line_total_cents: int,
     *   options: array<int, array{product_option: ProductOption, quantity: int, unit_price_cents: int, line_total_cents: int}>
     * }
     */
    private function resolveOrderItemLine(
        Product $product,
        Client $client,
        array $item,
        int $tenantId,
        ?OrderItem $existing = null
    ): array {
        $quantity = (float) $item['quantity'];

        if (isset($item['unit_price'])) {
            $unitPriceCents = (int) round(((float) $item['unit_price']) * 100);
            $this->assertDiscountWithinLimit($product, $client, $unitPriceCents);
        } elseif ($existing !== null && (int) $existing->product_id === (int) $product->id) {
            $unitPriceCents = (int) round(((float) $existing->unit_price) * 100);
        } else {
            $unitPriceCents = (int) round($this->pricingService->resolvePrice($product, $client) * 100);
        }

        $baseLineTotalCents = (int) round($unitPriceCents * $quantity);
        $options = $this->resolveOrderItemOptions(
            $product,
            is_array($item['options'] ?? null) ? $item['options'] : [],
            $tenantId,
            $quantity
        );

        return [
            'product' => $product,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'line_total_cents' => $baseLineTotalCents + array_sum(array_column($options, 'line_total_cents')),
            'options' => $options,
            'notes' => isset($item['notes']) && trim((string) $item['notes']) !== '' ? trim((string) $item['notes']) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectedOptions
     * @return array<int, array{product_option: ProductOption, quantity: int, unit_price_cents: int, line_total_cents: int}>
     */
    private function resolveOrderItemOptions(
        Product $product,
        array $selectedOptions,
        int $tenantId,
        float $itemQuantity
    ): array {
        $product->loadMissing(['optionGroups.options']);

        $activeGroups = $product->optionGroups
            ->filter(fn($group) => $group->is_active)
            ->values();

        if ($activeGroups->isEmpty() && $selectedOptions === []) {
            return [];
        }

        $optionsByUuid = [];
        $groupSelectionTotals = [];
        $resolved = [];

        foreach ($activeGroups as $group) {
            foreach ($group->options->filter(fn($option) => $option->is_available) as $option) {
                $option->setRelation('group', $group);
                $optionsByUuid[$option->uuid] = $option;
            }
        }

        foreach ($selectedOptions as $selectedOption) {
            $optionUuid = (string) ($selectedOption['product_option_uuid'] ?? '');
            $optionQuantity = max(1, (int) ($selectedOption['quantity'] ?? 1));

            $option = $optionsByUuid[$optionUuid] ?? null;

            if (!$option || (int) $option->tenant_id !== $tenantId) {
                throw new InvalidOrderStateException(__('messages.order.invalid_product_option'));
            }

            if (isset($resolved[$optionUuid])) {
                throw new InvalidOrderStateException(__('messages.order.duplicate_product_option'));
            }

            $groupUuid = (string) $option->group->uuid;
            $groupSelectionTotals[$groupUuid] = ($groupSelectionTotals[$groupUuid] ?? 0) + $optionQuantity;

            $unitPriceCents = (int) round(((float) $option->price) * 100);
            $resolved[$optionUuid] = [
                'product_option' => $option,
                'quantity' => $optionQuantity,
                'unit_price_cents' => $unitPriceCents,
                'line_total_cents' => (int) round($unitPriceCents * $optionQuantity * $itemQuantity),
            ];
        }

        foreach ($activeGroups as $group) {
            $selectedCount = $groupSelectionTotals[$group->uuid] ?? 0;

            if ($selectedCount < (int) $group->min_select) {
                throw new InvalidOrderStateException(__('messages.order.option_group_min_select_not_met', [
                    'group' => $group->name,
                    'min' => $group->min_select,
                ]));
            }

            if ($selectedCount > (int) $group->max_select) {
                throw new InvalidOrderStateException(__('messages.order.option_group_max_select_exceeded', [
                    'group' => $group->name,
                    'max' => $group->max_select,
                ]));
            }
        }

        return array_values($resolved);
    }

    /**
     * @param array<int, array{product_option: ProductOption, quantity: int, unit_price_cents: int, line_total_cents: int}> $options
     */
    private function syncOrderItemOptions(OrderItem $orderItem, array $options): void
    {
        OrderItemOption::where('order_item_id', $orderItem->id)->delete();

        foreach ($options as $option) {
            OrderItemOption::create([
                'tenant_id' => $orderItem->tenant_id,
                'order_item_id' => $orderItem->id,
                'product_option_id' => $option['product_option']->id,
                'quantity' => $option['quantity'],
                'unit_price' => $this->centsToDecimal($option['unit_price_cents']),
                'line_total' => $this->centsToDecimal($option['line_total_cents']),
            ]);
        }
    }

    /**
     * @param array<int, array{product_option: ProductOption, quantity: int, unit_price_cents: int, line_total_cents: int}> $resolvedOptions
     */
    private function orderItemOptionsChanged(OrderItem $existing, array $resolvedOptions): bool
    {
        $current = $existing->options
            ->mapWithKeys(fn($option) => [
                $option->productOption->uuid => [
                    'quantity' => (int) $option->quantity,
                    'unit_price_cents' => (int) round(((float) $option->unit_price) * 100),
                ],
            ])
            ->all();

        $incoming = [];

        foreach ($resolvedOptions as $resolvedOption) {
            $incoming[$resolvedOption['product_option']->uuid] = [
                'quantity' => $resolvedOption['quantity'],
                'unit_price_cents' => $resolvedOption['unit_price_cents'],
            ];
        }

        ksort($current);
        ksort($incoming);

        return $current !== $incoming;
    }

    private function resolveDefaultStockLocation(int $tenantId): StockLocation
    {
        $default = StockLocation::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$default) {
            throw new InvalidOrderStateException(__('messages.order.no_default_stock_location'));
        }

        return $default;
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Ponto único de validação do limite de desconto por perfil (roadmap
     * A1.5). Chamado nos DOIS pontos de entrada reais de desconto manual
     * mapeados no código: create() e updateItems(), sempre que o item traz
     * `unit_price` explícito no payload (override manual de preço — o
     * único mecanismo de "desconto manual" que hoje existe, tanto pra
     * origin=staff/pdv quanto pra edição de pedido já criado). Compara o
     * unit_price informado contra o preço que teria sido resolvido sem
     * override ($this->pricingService->resolvePrice, mesmo preço "cheio"
     * usado como fallback) — a diferença pra baixo é o desconto sendo
     * aplicado pelo operador. Preço IGUAL ou MAIOR que o resolvido nunca é
     * bloqueado (acréscimo manual não é desconto). Sem ator autenticado
     * (Auth::id() null, ex.: nenhum caminho atual chama create()/
     * updateItems() fora de requisição HTTP) ou sem tenant_role resolvido
     * no container, a checagem é pulada — sem regressão em nenhum fluxo
     * hoje existente. discount_limit_percent não configurado (null) = sem
     * restrição, comportamento anterior a esta feature.
     */
    private function assertDiscountWithinLimit(Product $product, Client $client, int $unitPriceCents): void
    {
        if (!app()->bound('tenant_role')) {
            return;
        }

        $tenantRole = app('tenant_role');

        if (!$tenantRole) {
            return;
        }

        $limitPercent = $this->permissionService->resolveOrderDiscountLimitPercent((int) $tenantRole->id);

        if ($limitPercent === null) {
            return;
        }

        $expectedCents = (int) round($this->pricingService->resolvePrice($product, $client) * 100);

        if ($expectedCents <= 0 || $unitPriceCents >= $expectedCents) {
            return;
        }

        $discountPercent = (($expectedCents - $unitPriceCents) / $expectedCents) * 100;

        // Tolerância pequena pra arredondamento de centavos não gerar falso
        // positivo num desconto que bate exatamente no limite configurado.
        if ($discountPercent > $limitPercent + 0.01) {
            throw new DiscountLimitExceededException(
                __('messages.order.discount_limit_exceeded', ['limit' => rtrim(rtrim(number_format($limitPercent, 2), '0'), '.')])
            );
        }
    }

    /**
     * Trava a linha do Order antes de qualquer leitura de estado
     * (cancelled_at/is_paid/is_delivered) dentro de deliver/pay/
     * payInstallment/cancel — sem isso, duas ações concorrentes no mesmo
     * pedido (ex.: deliver() e a cascata de payInstallment() ao mesmo
     * tempo) podem ambas ler o estado "antigo" antes de qualquer uma
     * commitar. O saldo de estoque não corrompe de qualquer forma (o
     * StockService já se protege via lock em StockBalance), mas sem este
     * lock a segunda chamada cai numa exceção de Estoque não relacionada
     * ao real motivo (corrida), em vez do 422 limpo de estado inválido.
     */
    private function lockOrder(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Guard de updateItems(): edição de item só é permitida enquanto o
     * pedido não está entregue/pago/cancelado (escopo deliberadamente
     * limitado, ver PHPDoc de updateItems()).
     */
    private function assertOrderItemsEditable(Order $order): void
    {
        if ($order->cancelled_at !== null) {
            throw new InvalidOrderStateException(__('messages.order.already_cancelled'));
        }

        if ($order->is_delivered) {
            throw new InvalidOrderStateException(__('messages.order.already_delivered'));
        }

        if ($order->is_paid) {
            throw new InvalidOrderStateException(__('messages.order.already_paid'));
        }
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR). 3º módulo relacional do
     * projeto, mesmo cuidado de Client/Product/Stock.
     */
    private function assertBelongsToCurrentTenant(BaseModel $model): void
    {
        if ((int) $model->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    /**
     * {order}/installments/{installment} não usa scoped bindings (rota
     * declarada manualmente, não Route::resource) — sem esta checagem, um
     * usuário do mesmo tenant poderia pagar a parcela de um pedido usando
     * o uuid de outro pedido na URL.
     */
    private function assertInstallmentBelongsToOrder(Order $order, OrderInstallment $installment): void
    {
        if ((int) $installment->order_id !== (int) $order->id) {
            abort(404);
        }
    }
}
