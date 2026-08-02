<?php

namespace App\Services\Sale;

use App\DTOs\Sale\CancelSaleDTO;
use App\DTOs\Sale\CreateSaleDTO;
use App\DTOs\Sale\UpdateSaleItemsDTO;
use App\Events\Sale\SaleApproved;
use App\Events\Sale\SaleCancelled;
use App\Events\Sale\SaleCreated;
use App\Events\Sale\SaleDelivered;
use App\Events\Sale\SaleInstallmentPaid;
use App\Events\Sale\SaleInstallmentUnpaid;
use App\Events\Sale\SaleItemsUpdated;
use App\Events\Sale\SalePaid;
use App\Events\Sale\SalePartiallyPaid;
use App\Events\Sale\SaleRejected;
use App\Events\Sale\SaleUndelivered;
use App\Events\Sale\SaleUnpaid;
use App\Events\Sale\SaleCancellationRequested;
use App\Events\Sale\SaleCancellationApproved;
use App\Events\Sale\SaleCancellationRejected;
use App\Exceptions\InvalidSaleStateException;
use App\Models\BaseModel;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleInstallment;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SalePrepLink;
use App\Models\Event\EventProduct;
use App\Models\Event\TicketType;
use App\Models\Storefront\CouponRedemption;
use App\Exceptions\DiscountLimitExceededException;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Services\Permission\PermissionService;
use App\Services\Workflow\WorkflowTransitionLogger;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Módulo mais complexo do projeto: Pedido/Venda, com cascata de quitação
 * (última parcela paga marca o pedido inteiro como pago+entregue).
 * Controle de quantidade de ingresso vendida é responsabilidade de
 * TicketType/TicketBatch (quantity_available/quantity_sold), não deste
 * service — não há mais integração com estoque físico. Ver
 * `.claude/memory/architecture-decisions.md` para o desenho completo.
 */
class SaleService
{
    // finalCustomerLink NÃO entra aqui de propósito: a relation depende de
    // $this->tenant_id do PRÓPRIO Sale (ver Sale::finalCustomerLink()),
    // que só é correto em acesso LAZY sobre uma instância real já
    // carregada — com with()/load(), Eloquent constrói a query da relation
    // a partir de um model "stub" vazio (tenant_id null), o que zeraria o
    // resultado pra todo pedido. Resources acessam
    // $order->finalCustomerLink diretamente (lazy, 1 query por pedido),
    // nunca via este array.
    public const EAGER_RELATIONS = ['finalCustomer', 'items.ticketType', 'items.eventProduct', 'items.seat', 'installments', 'coupon', 'rating'];
    public const LIST_EAGER_RELATIONS = ['finalCustomer'];

    public function __construct(
        private SaleRepositoryInterface $repository,
        private ParcelaVencimentoCalculator $vencimentoCalculator,
        private SalePaymentService $paymentService,
        private PermissionService $permissionService,
        private WorkflowTransitionLogger $workflowTransitionLogger,
    ) {
    }

    public function find(Sale $order): Sale
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
    public function generatePrepLink(Sale $order): SalePrepLink
    {
        $this->assertBelongsToCurrentTenant($order);

        return SalePrepLink::create([
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
            'client_name' => 'final_customers.name',
            'total_amount' => 'orders.total_amount',
            'is_installment' => 'orders.is_installment',
            'is_paid' => 'orders.is_paid',
            'is_delivered' => 'orders.is_delivered',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $needsClientJoin = $sortColumn === 'final_customers.name';

        $query = Sale::query()
            ->select([
                'orders.id',
                'orders.uuid',
                'orders.final_customer_id',
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
                'orders.created_at',
            ])
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->with(self::LIST_EAGER_RELATIONS);

        if ($needsClientJoin) {
            $query->leftJoin('final_customers', 'final_customers.id', '=', 'orders.final_customer_id');
        }

        if (!empty($filters['client_name'])) {
            $query->whereHas('finalCustomer', fn($q) => $q->where('name', 'like', '%' . $filters['client_name'] . '%'));
        }

        if (!empty($filters['client_uuid'])) {
            $query->whereHas('finalCustomer', fn($q) => $q->where('uuid', $filters['client_uuid']));
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

        // Tela dedicada de gestão de vendas online (storefront-orders,*)
        // força origin=storefront no controller, nunca lido cru do request.
        if (!empty($filters['origin'])) {
            $normalizedOrigin = Sale::normalizeOrigin((string) $filters['origin']);

            if ($normalizedOrigin === 'staff') {
                $query->whereIn('orders.origin', ['staff', 'counter']);
            } else {
                $query->where('orders.origin', $normalizedOrigin);
            }
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
                'confirmed' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.status', 'confirmed')
                    ->where('orders.is_delivered', false),
                'financial_pending' => $query
                    ->whereNull('orders.cancelled_at')
                    ->where('orders.is_delivered', true)
                    ->where('orders.is_paid', false),
                default => null,
            };
        }

        // "Somente ativos" (grid de gestão de vendas online) — filtro
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
    public function create(CreateSaleDTO $dto): Sale
    {
        return DB::transaction(function () use ($dto) {
            // final_customers é GLOBAL (sem tenant_id) — a checagem de
            // "pertence a este tenant" (antes feita via clients.tenant_id)
            // passa a ser: existe um FinalCustomerTenantLink confirmando
            // esse FinalCustomer nesta loja.
            $client = FinalCustomer::where('uuid', $dto->finalCustomerUuid)->firstOrFail();

            FinalCustomerTenantLink::where('final_customer_id', $client->id)
                ->where('tenant_id', $dto->tenantId)
                ->firstOrFail();

            $lines = [];
            $totalCents = 0;

            foreach ($dto->items as $item) {
                $sellable = $this->resolveSellable($item, $dto->tenantId);

                $line = $this->resolveOrderItemLine($sellable, $client, $item);

                $totalCents += $line['line_total_cents'];
                $lines[] = $line;
            }

            // Taxa de serviço do fluxo presencial legado — acréscimo somado
            // ao total, persistida em coluna própria (service_fee) para o
            // breakdown. default 0.0 preserva os fluxos internos e da loja.
            $serviceFeeCents = (int) round($dto->serviceFee * 100);
            $totalCents += $serviceFeeCents;

            // Desconto de cupom (roadmap Delivery, Fase 3) — subtraído
            // depois da taxa de serviço já somada (mesma ordem do plano:
            // subtotal + entrega - desconto), max(0, ...) defensivo pra
            // nunca persistir total_amount negativo mesmo num cenário
            // inesperado de desconto maior que o total. default 0.0
            // preserva 100% o fluxo staff existente (nunca usa cupom).
            $discountAmountCents = (int) round($dto->discountAmount * 100);
            $totalCents = max(0, $totalCents - $discountAmountCents);

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
                'final_customer_id' => $client->id,
                'codigo' => $codigo,
                'is_installment' => $dto->isInstallment,
                'total_amount' => $this->centsToDecimal($totalCents),
                'service_fee' => $dto->serviceFee,
                'coupon_id' => $dto->couponId,
                'discount_amount' => $dto->discountAmount,
                'is_paid' => false,
                'is_delivered' => false,
                'due_date' => $dueDate,
                'notes' => $dto->notes,
                'payment_method' => $dto->paymentMethod,
                'needs_change' => $dto->needsChange,
                'change_for_amount' => $dto->changeForAmount,
                'status' => $dto->status,
                'origin' => $dto->origin,
            ]);

            foreach ($lines as $line) {
                $orderItem = SaleItem::create([
                    'tenant_id' => $dto->tenantId,
                    'order_id' => $order->id,
                    'ticket_type_id' => $line['is_ticket_type'] ? $line['sellable']->id : null,
                    'event_product_id' => $line['is_ticket_type'] ? null : $line['sellable']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->centsToDecimal($line['unit_price_cents']),
                    'line_total' => $this->centsToDecimal($line['line_total_cents']),
                    'notes' => $line['notes'],
                    'attendee_data' => $line['attendee_data'],
                ]);
            }

            if ($dto->isInstallment) {
                $this->createInstallments($order, $dto->installmentsCount, $totalCents);
            }

            event(new SaleCreated(
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
            // bloqueado na validação (StoreSaleRequest), guard aqui é só
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
     * SaleInstallmentService::reallocate() (diff do array inteiro contra
     * o estado atual via uuid).
     *
     * Pedido parcelado: total_amount muda mas as installments NÃO são
     * recalculadas automaticamente aqui (decisão intencional — usuário
     * ajusta via PUT /orders/{order}/installments depois).
     */
    public function updateItems(Sale $order, UpdateSaleItemsDTO $dto): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $dto) {
            $order = $this->lockOrder($order);

            $this->assertOrderItemsEditable($order);

            $order->loadMissing('finalCustomer');
            $client = $order->finalCustomer;

            $currentItems = SaleItem::where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('uuid');

            // Validação do payload inteiro ANTES de mutar qualquer coisa
            // — mesmo cuidado "tudo ou nada" de SaleInstallmentService::reallocate().
            $seenUuids = [];

            foreach ($dto->items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid === null) {
                    continue;
                }

                if (in_array($uuid, $seenUuids, true)) {
                    throw new InvalidSaleStateException(__('messages.order.item_duplicate_reference'));
                }
                $seenUuids[] = $uuid;

                if (!$currentItems->has($uuid)) {
                    throw new InvalidSaleStateException(__('messages.order.item_not_found_in_order'));
                }
            }

            // Itens novos (sem uuid): cria, igual a create().
            foreach ($dto->items as $item) {
                if (($item['uuid'] ?? null) !== null) {
                    continue;
                }

                $sellable = $this->resolveSellable($item, $order->tenant_id);
                $line = $this->resolveOrderItemLine($sellable, $client, $item);

                SaleItem::create([
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $line['is_ticket_type'] ? $sellable->id : null,
                    'event_product_id' => $line['is_ticket_type'] ? null : $sellable->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->centsToDecimal($line['unit_price_cents']),
                    'line_total' => $this->centsToDecimal($line['line_total_cents']),
                    'notes' => $line['notes'],
                ]);
            }

            // Itens existentes referenciados no payload (com uuid): atualiza.
            foreach ($dto->items as $item) {
                $uuid = $item['uuid'] ?? null;

                if ($uuid === null) {
                    continue;
                }

                $existing = $currentItems->get($uuid);

                $sellable = $this->resolveSellable($item, $order->tenant_id);
                $isTicketType = $sellable instanceof TicketType;

                $line = $this->resolveOrderItemLine($sellable, $client, $item, $existing);
                $quantity = $line['quantity'];

                $existing->ticket_type_id = $isTicketType ? $sellable->id : null;
                $existing->event_product_id = $isTicketType ? null : $sellable->id;
                $existing->quantity = $quantity;
                $existing->unit_price = $this->centsToDecimal($line['unit_price_cents']);
                $existing->line_total = $this->centsToDecimal($line['line_total_cents']);
                $existing->notes = $line['notes'];
                $existing->save();
            }

            // Itens existentes NÃO referenciados no payload: remove.
            foreach ($currentItems as $uuid => $existing) {
                if (in_array($uuid, $seenUuids, true)) {
                    continue;
                }

                $existing->delete();
            }

            $totalCents = (int) round(
                (float) SaleItem::where('order_id', $order->id)->whereNull('deleted_at')->sum('line_total') * 100
            );

            $order->total_amount = $this->centsToDecimal($totalCents);

            if ($dto->notes !== null) {
                $order->notes = $dto->notes;
            }

            $order->save();

            event(new SaleItemsUpdated(
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            return $order->fresh()->load(self::EAGER_RELATIONS);
        });
    }

    public function deliver(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if ($order->is_delivered) {
                throw new InvalidSaleStateException(__('messages.order.already_delivered'));
            }

            return $this->performDelivery($order);
        });
    }

    /**
     * Pedido de origem storefront (Fase 1) nasce `pending_approval` e só
     * pode ser entregue/pago depois que o staff aprova (status vira
     * `confirmed`) — sem essa checagem, deliver()/pay()/payInstallment()
     * contornam a fila de aprovação por completo (achado de code review:
     * um pedido nunca aprovado, ou explicitamente `rejected`, podia ser
     * entregue/pago normalmente sem nunca ter passado pela aprovação).
     */
    private function assertOrderIsApproved(Sale $order): void
    {
        if ($order->status === 'pending_approval') {
            throw new InvalidSaleStateException(__('messages.order.awaiting_approval'));
        }

        if ($order->status === 'rejected') {
            throw new InvalidSaleStateException(__('messages.order.order_rejected'));
        }
    }

    /**
     * Desfaz deliver(): devolve o estoque convertido em saída de volta
     * para RESERVADO (não só "disponível") — ver performUndelivery() para
     * o porquê dos dois passos por item. Pedido continua ativo (diferente
     * de cancel(), que é definitivo).
     */
    public function undeliver(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if (!$order->is_delivered) {
                throw new InvalidSaleStateException(__('messages.order.not_delivered'));
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
     *   entrega. Dispara SalePartiallyPaid em vez de SalePaid.
     */
    public function pay(Sale $order, ?string $paidAt = null, ?float $amount = null): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        if ($order->is_installment) {
            throw new InvalidSaleStateException(__('messages.order.use_installment_pay'));
        }

        return DB::transaction(function () use ($order, $paidAt, $amount) {
            $order = $this->lockOrder($order);

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if ($order->is_paid) {
                throw new InvalidSaleStateException(__('messages.order.already_paid'));
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
    public function unpay(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        if ($order->is_installment) {
            throw new InvalidSaleStateException(__('messages.order.use_installment_unpay'));
        }

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if (!$order->is_paid) {
                throw new InvalidSaleStateException(__('messages.order.not_paid'));
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
    public function payInstallment(Sale $order, SaleInstallment $installment, ?string $paidAt = null): Sale
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        if (!$order->is_installment) {
            throw new InvalidSaleStateException(__('messages.order.not_installment'));
        }

        return DB::transaction(function () use ($order, $installment, $paidAt) {
            $order = $this->lockOrder($order);
            $installment = SaleInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            $this->assertOrderIsApproved($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if ($installment->is_paid) {
                throw new InvalidSaleStateException(__('messages.order.installment_already_paid'));
            }

            $installment->is_paid = true;
            $installment->paid_at = $paidAt ? \Carbon\Carbon::parse($paidAt) : now();
            $installment->save();

            event(new SaleInstallmentPaid(
                orderUuid: $order->uuid,
                installmentUuid: $installment->uuid,
                actorId: Auth::id()
            ));

            $allInstallmentsPaid = SaleInstallment::where('order_id', $order->id)
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
    public function unpayInstallment(Sale $order, SaleInstallment $installment): Sale
    {
        $this->assertBelongsToCurrentTenant($order);
        $this->assertBelongsToCurrentTenant($installment);
        $this->assertInstallmentBelongsToOrder($order, $installment);

        if (!$order->is_installment) {
            throw new InvalidSaleStateException(__('messages.order.not_installment'));
        }

        return DB::transaction(function () use ($order, $installment) {
            $order = $this->lockOrder($order);
            $installment = SaleInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            if (!$installment->is_paid) {
                throw new InvalidSaleStateException(__('messages.order.installment_not_paid'));
            }

            $installment->is_paid = false;
            $installment->paid_at = null;
            $installment->save();

            event(new SaleInstallmentUnpaid(
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
     * o pedido não parcelado) já estiver paga.
     */
    public function cancel(Sale $order, CancelSaleDTO $dto): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $dto) {
            $order = $this->lockOrder($order);
            $fromStage = $this->workflowTransitionLogger->resolveOrderStage($order);

            if ($order->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
            }

            $hasPaidAmount = $order->is_installment
                ? SaleInstallment::where('order_id', $order->id)
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
                throw new InvalidSaleStateException(__('messages.order.cannot_cancel_paid'));
            }

            $order->cancelled_at = now();
            $order->cancellation_reason = $dto->cancellationReason;
            $order->save();

            // Pedido pago via Pix sendo cancelado: gera um Refund pendente
            // (não apaga o pagamento) — roadmap 2A.
            if ($paidPixCharge !== null) {
                $this->paymentService->createRefundForPaidCancellation($order);
            }

            event(new SaleCancelled(
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
    public function approve(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'pending_approval') {
                throw new InvalidSaleStateException(__('messages.order.not_pending_approval'));
            }

            $order->status = 'confirmed';
            $order->save();

            event(new SaleApproved(
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
     * Recusa um pedido pendente de aprovação. NÃO seta cancelled_at (fica
     * null) — a distinção "recusado" fica só no campo status, pra não
     * colidir com relatórios que hoje derivam "foi cancelado" de
     * cancelled_at !== null.
     */
    public function reject(Sale $order, ?string $reason): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order, $reason) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'pending_approval') {
                throw new InvalidSaleStateException(__('messages.order.not_pending_approval'));
            }

            // Pedido recusado nunca deve consumir o limite de uso do
            // cliente sobre um cupom — roadmap Delivery, Fase 3.
            if ($order->coupon_id !== null) {
                CouponRedemption::where('order_id', $order->id)->delete();
            }

            $order->status = 'rejected';
            $order->cancellation_reason = $reason;
            $order->save();

            event(new SaleRejected(
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
    public function requestCancellation(Sale $order, ?string $reason, ?string $finalCustomerUuid = null): Sale
    {
        return DB::transaction(function () use ($order, $reason, $finalCustomerUuid) {
            $order = $this->lockOrder($order);

            $this->assertCancellationRequestEligible($order);

            $order->status_before_cancellation_request = $order->status;
            $order->status = 'cancellation_requested';
            $order->cancellation_reason = $reason;
            $order->save();

            event(new SaleCancellationRequested(
                orderUuid: $order->uuid,
                reason: $reason,
                finalCustomerUuid: $finalCustomerUuid,
            ));

            return $order;
        });
    }

    /**
     * Só o cliente final pode solicitar (decisão de produto), e só
     * enquanto o pedido não passou de "entregue" (flag is_delivered), não
     * pelo enum de status. Pedido já `rejected`/já cancelado/já com
     * solicitação em aberto também bloqueiam (nada a cancelar/duplicar).
     */
    private function assertCancellationRequestEligible(Sale $order): void
    {
        if ($order->origin !== 'storefront') {
            throw new InvalidSaleStateException(__('messages.order.cancellation_request_not_storefront'));
        }

        if ($order->cancelled_at !== null) {
            throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
        }

        if ($order->status === 'cancellation_requested') {
            throw new InvalidSaleStateException(__('messages.order.cancellation_already_requested'));
        }

        if ($order->status === 'rejected') {
            throw new InvalidSaleStateException(__('messages.order.order_rejected'));
        }

        if ($order->is_delivered) {
            throw new InvalidSaleStateException(__('messages.order.already_delivered'));
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
    public function approveCancellationRequest(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'cancellation_requested') {
                throw new InvalidSaleStateException(__('messages.order.no_cancellation_requested'));
            }

            $previousStatus = $order->status_before_cancellation_request ?? 'confirmed';
            $reason = $order->cancellation_reason ?: __('messages.order.cancellation_requested_by_client_default');

            $order = $this->cancel($order, new CancelSaleDTO(cancellationReason: $reason));

            $order->status = $previousStatus;
            $order->status_before_cancellation_request = null;
            $order->save();

            event(new SaleCancellationApproved(
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
    public function rejectCancellationRequest(Sale $order): Sale
    {
        $this->assertBelongsToCurrentTenant($order);

        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);

            if ($order->status !== 'cancellation_requested') {
                throw new InvalidSaleStateException(__('messages.order.no_cancellation_requested'));
            }

            $order->status = $order->status_before_cancellation_request ?? 'confirmed';
            $order->status_before_cancellation_request = null;
            $order->cancellation_reason = null;
            $order->save();

            event(new SaleCancellationRejected(
                orderUuid: $order->uuid,
                actorId: Auth::id()
            ));

            return $order;
        });
    }

    /**
     * Marca o pedido como entregue. Sem guard/lock/transação próprios —
     * quem chama já garantiu a validade da transição (deliver() valida
     * cancelled_at/is_delivered e trava a linha; create() está criando a
     * linha agora, sem concorrência possível; a cascata de
     * payInstallment() já trava a linha e checa is_delivered antes de
     * chamar). Reaproveitado nos 3 lugares.
     */
    private function performDelivery(Sale $order): Sale
    {
        $fromStage = 'confirmed';
        $order->is_delivered = true;
        $order->delivered_at = now();
        $order->save();

        event(new SaleDelivered(
            orderId: $order->id,
            orderUuid: $order->uuid,
            fromStage: $fromStage,
            toStage: $this->workflowTransitionLogger->resolveOrderStage($order),
            actorId: Auth::id()
        ));

        return $order;
    }

    /**
     * Inverso de performDelivery(): desmarca o pedido como entregue. Sem
     * guard/lock/transação próprios — mesma convenção de performDelivery().
     */
    private function performUndelivery(Sale $order): Sale
    {
        $order->is_delivered = false;
        $order->delivered_at = null;
        $order->save();

        event(new SaleUndelivered(
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
    private function performPayment(Sale $order, ?\Carbon\CarbonInterface $paidAt = null): Sale
    {
        $order->is_paid = true;
        $order->paid_at = $paidAt ?? now();
        // Pago total sempre implica paid_amount = total_amount, mesmo
        // quando chegou aqui via um pay() com $amount >= total_amount ou
        // via a cascata de payInstallment() — mantém a mesma invariante
        // do pagamento parcial (paid_amount nunca "sobra" desatualizado).
        $order->paid_amount = $order->total_amount;
        $order->save();

        event(new SalePaid(
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
    private function performUnpayment(Sale $order): Sale
    {
        $order->is_paid = false;
        $order->paid_at = null;
        $order->paid_amount = null;
        $order->save();

        event(new SaleUnpaid(
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
    private function performPartialPayment(Sale $order, float $amount): Sale
    {
        $order->paid_amount = $this->centsToDecimal((int) round($amount * 100));
        $order->save();

        event(new SalePartiallyPaid(
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
    private function createInstallments(Sale $order, int $installmentsCount, int $totalCents): void
    {
        $baseCents = intdiv($totalCents, $installmentsCount);
        $remainderCents = $totalCents - ($baseCents * $installmentsCount);

        for ($number = 1; $number <= $installmentsCount; $number++) {
            $amountCents = $baseCents + ($number === $installmentsCount ? $remainderCents : 0);

            $dueDate = $this->vencimentoCalculator->calculateDueDate(now()->addMonthsNoOverflow($number - 1));

            SaleInstallment::create([
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
     * Resolve o item vendável (ingresso OU adicional/estacionamento) a
     * partir do payload — exatamente um de ticket_type_uuid/
     * event_product_uuid deve estar presente (garantido pelo Request, mas
     * revalidado aqui defensivamente, já que create()/updateItems() também
     * são chamados por StorefrontCheckoutService).
     */
    private function resolveSellable(array $item, int $tenantId): TicketType|EventProduct
    {
        $ticketTypeUuid = $item['ticket_type_uuid'] ?? null;
        $eventProductUuid = $item['event_product_uuid'] ?? null;

        if (($ticketTypeUuid === null) === ($eventProductUuid === null)) {
            throw new InvalidSaleStateException(__('messages.order.item_missing_sellable'));
        }

        if ($ticketTypeUuid !== null) {
            return TicketType::where('uuid', $ticketTypeUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();
        }

        return EventProduct::where('uuid', $eventProductUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *   sellable: TicketType|EventProduct,
     *   is_ticket_type: bool,
     *   quantity: float,
     *   unit_price_cents: int,
     *   line_total_cents: int,
     *   notes: ?string,
     *   attendee_data: ?array
     * }
     */
    private function resolveOrderItemLine(
        TicketType|EventProduct $sellable,
        FinalCustomer $client,
        array $item,
        ?SaleItem $existing = null
    ): array {
        $quantity = (float) $item['quantity'];
        $isTicketType = $sellable instanceof TicketType;

        $sameAsExisting = $existing !== null && (
            $isTicketType
                ? (int) $existing->ticket_type_id === (int) $sellable->id
                : (int) $existing->event_product_id === (int) $sellable->id
        );

        if (isset($item['unit_price'])) {
            $unitPriceCents = (int) round(((float) $item['unit_price']) * 100);
            $this->assertDiscountWithinLimit($sellable, $client, $unitPriceCents);
        } elseif ($sameAsExisting) {
            $unitPriceCents = (int) round(((float) $existing->unit_price) * 100);
        } else {
            $unitPriceCents = (int) round(((float) $sellable->price) * 100);
        }

        $lineTotalCents = (int) round($unitPriceCents * $quantity);

        return [
            'sellable' => $sellable,
            'is_ticket_type' => $isTicketType,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
            'line_total_cents' => $lineTotalCents,
            'notes' => isset($item['notes']) && trim((string) $item['notes']) !== '' ? trim((string) $item['notes']) : null,
            // Participantes do checkout (spec 5.10) — só faz sentido para
            // item de TicketType, consumido por TicketIssuanceService na
            // emissão. array vazio normalizado para null.
            'attendee_data' => $isTicketType && !empty($item['attendee_data']) ? array_values($item['attendee_data']) : null,
        ];
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
     * pedidos internos quanto pra edição de pedido já criado). Compara o
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
    private function assertDiscountWithinLimit(TicketType|EventProduct $sellable, FinalCustomer $client, int $unitPriceCents): void
    {
        if (!app()->bound('tenant_role')) {
            return;
        }

        $tenantRole = app('tenant_role');

        if (!$tenantRole) {
            return;
        }

        $limitPercent = $this->permissionService->resolveSaleDiscountLimitPercent((int) $tenantRole->id);

        if ($limitPercent === null) {
            return;
        }

        $expectedCents = (int) round(((float) $sellable->price) * 100);

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
     * Trava a linha do Sale antes de qualquer leitura de estado
     * (cancelled_at/is_paid/is_delivered) dentro de deliver/pay/
     * payInstallment/cancel — sem isso, duas ações concorrentes no mesmo
     * pedido (ex.: deliver() e a cascata de payInstallment() ao mesmo
     * tempo) podem ambas ler o estado "antigo" antes de qualquer uma
     * commitar, causando dupla transição a partir do mesmo estado.
     */
    private function lockOrder(Sale $order): Sale
    {
        return Sale::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Guard de updateItems(): edição de item só é permitida enquanto o
     * pedido não está entregue/pago/cancelado (escopo deliberadamente
     * limitado, ver PHPDoc de updateItems()).
     */
    private function assertOrderItemsEditable(Sale $order): void
    {
        if ($order->cancelled_at !== null) {
            throw new InvalidSaleStateException(__('messages.order.already_cancelled'));
        }

        if ($order->is_delivered) {
            throw new InvalidSaleStateException(__('messages.order.already_delivered'));
        }

        if ($order->is_paid) {
            throw new InvalidSaleStateException(__('messages.order.already_paid'));
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
    private function assertInstallmentBelongsToOrder(Sale $order, SaleInstallment $installment): void
    {
        if ((int) $installment->order_id !== (int) $order->id) {
            abort(404);
        }
    }
}
