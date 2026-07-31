<?php

namespace App\Services\Balcao;

use App\DTOs\Balcao\AddComandaItemDTO;
use App\DTOs\Balcao\CloseComandaDTO;
use App\DTOs\Balcao\OpenComandaDTO;
use App\DTOs\Order\CreateOrderDTO;
use App\Events\Balcao\ComandaClosed;
use App\Events\Balcao\ComandaItemAdded;
use App\Events\Balcao\ComandaOpened;
use App\Exceptions\Balcao\ComandaException;
use App\Exceptions\Pdv\PaymentAmountMismatchException;
use App\Models\Balcao\Comanda;
use App\Models\Balcao\ComandaItem;
use App\Models\Balcao\Station;
use App\Models\Balcao\Table;
use App\Models\Client\Client;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Tenant\TenantSettings;
use App\Repositories\Contracts\ComandaRepositoryInterface;
use App\Services\Order\OrderPaymentService;
use App\Services\Order\OrderService;
use App\Services\Product\ProductPricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ciclo de vida da comanda de balcão (roadmap Balcão, Fases 1+2). NÃO estende
 * Order — a comanda vive aberta e só materializa um Order (origin='counter')
 * no fechamento, reaproveitando 100% a infra de pedido/pagamento (mesma tabela
 * `payments` polimórfica já usada pelo PDV). O estoque NÃO é tocado aqui: a
 * baixa real acontece por item no envio à estação (ComandaItemService), então
 * o Order é criado com reserveStock=false/markAsDelivered=false e marcado como
 * entregue diretamente (decisão #3, evita double-exit em
 * OrderService::convertReservationsToExit).
 */
class ComandaService
{
    private const WALK_IN_CLIENT_NAME = 'Consumidor Final';

    public function __construct(
        private ComandaRepositoryInterface $repository,
        private ProductPricingService $pricingService,
        private OrderService $orderService,
        private OrderPaymentService $paymentService,
    ) {
    }

    public function listOpen(int $tenantId): Collection
    {
        return $this->repository->listOpenForTenant($tenantId);
    }

    public function find(int $tenantId, string $uuid): Comanda
    {
        $comanda = $this->repository->findByUuidForTenant($uuid, $tenantId);

        if ($comanda === null) {
            abort(404);
        }

        return $comanda;
    }

    public function open(int $tenantId, OpenComandaDTO $dto): Comanda
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            if ($dto->clientComandaUuid !== null) {
                $existing = Comanda::query()
                    ->where('tenant_id', $tenantId)
                    ->where('client_comanda_uuid', $dto->clientComandaUuid)
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['table', 'items.product', 'items.station']);
                }
            }

            $table = null;

            if ($dto->tableUuid !== null) {
                $table = Table::where('uuid', $dto->tableUuid)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($table === null) {
                    abort(404);
                }
            }

            // Taxa de serviço congelada no momento da abertura (decisão #5) —
            // mudanças posteriores na config do tenant não afetam esta comanda.
            $settings = TenantSettings::where('tenant_id', $tenantId)->first();

            $comanda = $this->repository->create([
                'tenant_id' => $tenantId,
                'table_id' => $table?->id,
                'label' => $dto->label,
                'status' => Comanda::STATUS_OPEN,
                'opened_by' => Auth::id(),
                'opened_at' => now(),
                'service_fee_percent' => $settings?->service_fee_percent,
                'client_comanda_uuid' => $dto->clientComandaUuid,
            ]);

            if ($table !== null && in_array($table->status, [Table::STATUS_FREE, Table::STATUS_RESERVED], true)) {
                $table->status = Table::STATUS_OCCUPIED;
                $table->save();
            }

            event(new ComandaOpened(
                comandaUuid: $comanda->uuid,
                tableUuid: $table?->uuid,
                actorId: (int) Auth::id()
            ));

            return $comanda->load(['table', 'items.product', 'items.station']);
        });
    }

    public function addItem(int $tenantId, string $comandaUuid, AddComandaItemDTO $dto): ComandaItem
    {
        return DB::transaction(function () use ($tenantId, $comandaUuid, $dto) {
            $comanda = $this->resolveOpenComanda($tenantId, $comandaUuid, true);

            if ($dto->clientItemUuid !== null) {
                $existing = ComandaItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('client_item_uuid', $dto->clientItemUuid)
                    ->where('comanda_id', $comanda->id)
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['product', 'station']);
                }
            }

            $product = Product::where('uuid', $dto->productUuid)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $stationId = $this->resolveStationId($product);

            // Preço congelado no momento de adicionar (comanda não tem cliente
            // cadastrado — divisão por pessoa usa `label`, não Client — então
            // a resolução cai no Product.price, sem override de categoria).
            $unitPrice = $this->pricingService->resolvePrice($product, null);

            $item = ComandaItem::create([
                'tenant_id' => $tenantId,
                'comanda_id' => $comanda->id,
                'product_id' => $product->id,
                'station_id' => $stationId,
                'qty' => $dto->qty,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'notes' => $dto->notes,
                'prep_status' => ComandaItem::STATUS_QUEUED,
                'added_by' => Auth::id(),
                'client_item_uuid' => $dto->clientItemUuid,
            ]);

            event(new ComandaItemAdded(
                comandaUuid: $comanda->uuid,
                itemUuid: $item->uuid,
                actorId: (int) Auth::id()
            ));

            return $item->load(['product', 'station']);
        });
    }

    /**
     * Fecha a comanda: soma itens não cancelados + taxa de serviço, materializa
     * o Order (origin='counter'), marca entregue direto (decisão #3), valida e
     * registra as formas de pagamento (soma == total), libera a mesa. Idempotente
     * contra fechamento duplo: a comanda é travada e re-lida DEPOIS do lock; uma
     * segunda tentativa vê status=closed e é rejeitada, nunca gera 2º Order.
     */
    public function close(int $tenantId, string $comandaUuid, CloseComandaDTO $dto): Order
    {
        return DB::transaction(function () use ($tenantId, $comandaUuid, $dto) {
            $scoped = $this->repository->findByUuidForTenant($comandaUuid, $tenantId);

            if ($scoped === null) {
                abort(404);
            }

            $comanda = Comanda::whereKey($scoped->id)->lockForUpdate()->firstOrFail();

            if ($comanda->status === Comanda::STATUS_CLOSED) {
                throw new ComandaException(__('messages.comanda.already_closed'));
            }

            if ($comanda->status === Comanda::STATUS_CANCELLED) {
                throw new ComandaException(__('messages.comanda.already_cancelled'));
            }

            $items = ComandaItem::where('comanda_id', $comanda->id)
                ->where('prep_status', '!=', ComandaItem::STATUS_CANCELLED)
                ->whereNull('deleted_at')
                ->get();

            if ($items->isEmpty()) {
                throw new ComandaException(__('messages.comanda.no_items_to_close'));
            }

            $orderItems = [];
            $subtotalCents = 0;

            foreach ($items as $item) {
                $unitPriceCents = (int) round((float) $item->unit_price * 100);
                $subtotalCents += (int) round($unitPriceCents * (float) $item->qty);

                $orderItems[] = [
                    'product_uuid' => $item->product->uuid,
                    'quantity' => (float) $item->qty,
                    'unit_price' => (float) $item->unit_price,
                ];
            }

            $serviceFee = $this->resolveServiceFee($tenantId, $comanda, $subtotalCents, $dto->applyServiceFee);

            $orderDto = new CreateOrderDTO(
                tenantId: $tenantId,
                clientUuid: $this->resolveWalkInClientUuid($tenantId),
                stockLocationUuid: null,
                isInstallment: false,
                installmentsCount: null,
                notes: null,
                expectedDeliveryDate: null,
                markAsDelivered: false,
                markAsPaid: false,
                items: $orderItems,
                origin: 'counter',
                status: 'confirmed',
                reserveStock: false,
                serviceFee: $serviceFee,
            );

            $order = $this->orderService->create($orderDto);

            // Entrega marcada DIRETO (decisão #3): a baixa real de estoque já
            // ocorreu por item no envio à estação; passar por performDelivery()
            // chamaria exit() de novo (convertReservationsToExit), duplicando a
            // baixa. Aqui só registramos o fato de entrega.
            $order->is_delivered = true;
            $order->delivered_at = now();
            $order->save();

            // Total autoritativo (calculado pelo backend, já com a taxa de
            // serviço) contra a soma das formas — nunca confia no request.
            $totalCents = (int) round((float) $order->total_amount * 100);
            $paidCents = 0;

            foreach ($dto->payments as $payment) {
                $paidCents += (int) round(((float) $payment['amount']) * 100);
            }

            if ($paidCents !== $totalCents) {
                throw new PaymentAmountMismatchException(__('messages.comanda.payment_mismatch'));
            }

            foreach ($dto->payments as $payment) {
                $this->paymentService->registerManualPayment(
                    $order,
                    (string) $payment['method'],
                    (float) $payment['amount']
                );
            }

            // Soma bateu: marca o pedido como pago reaproveitando o fluxo
            // existente (mesma trilha de um pedido staff/pdv pago).
            $order = $this->orderService->pay($order);

            $comanda->order_id = $order->id;
            $comanda->status = Comanda::STATUS_CLOSED;
            $comanda->closed_at = now();
            $comanda->save();

            $this->releaseTableIfLast($comanda);

            event(new ComandaClosed(
                comandaUuid: $comanda->uuid,
                orderUuid: $order->uuid,
                total: (float) $order->total_amount,
                serviceFee: $serviceFee,
                payments: array_map(
                    fn ($p) => ['method' => (string) $p['method'], 'amount' => (float) $p['amount']],
                    $dto->payments
                ),
                actorId: (int) Auth::id()
            ));

            return $order->load(OrderService::EAGER_RELATIONS);
        });
    }

    /**
     * Taxa de serviço aplicada no fechamento: só quando há percentual
     * congelado (>0) E (o tenant a marcou como obrigatória OU o garçom não a
     * recusou). Retorna o VALOR em reais (não o percentual).
     */
    private function resolveServiceFee(int $tenantId, Comanda $comanda, int $subtotalCents, bool $applyRequested): float
    {
        $percent = (float) $comanda->service_fee_percent;

        if ($percent <= 0) {
            return 0.0;
        }

        $mandatory = (bool) (TenantSettings::where('tenant_id', $tenantId)->value('service_fee_mandatory'));

        if (!$mandatory && !$applyRequested) {
            return 0.0;
        }

        $feeCents = (int) round($subtotalCents * $percent / 100);

        return $feeCents / 100;
    }

    private function releaseTableIfLast(Comanda $comanda): void
    {
        if ($comanda->table_id === null) {
            return;
        }

        if ($this->repository->hasOtherOpenComandaOnTable((int) $comanda->table_id, (int) $comanda->id)) {
            return;
        }

        Table::where('id', $comanda->table_id)
            ->update(['status' => Table::STATUS_FREE]);
    }

    /**
     * Resolve station_id da CATEGORIA do produto (decisão #4): produto →
     * product_type → product_category.station_id. Produto sem categoria
     * roteada = item avulso (station_id null, "sem estação").
     */
    private function resolveStationId(Product $product): ?int
    {
        if ($product->product_type_id === null) {
            return null;
        }

        $stationId = DB::table('product_categories')
            ->join('product_types', 'product_types.product_category_id', '=', 'product_categories.id')
            ->where('product_types.id', $product->product_type_id)
            ->whereNull('product_categories.deleted_at')
            ->value('product_categories.station_id');

        return $stationId !== null ? (int) $stationId : null;
    }

    /**
     * Comanda por uuid + tenant, garantindo que ainda está aberta. 404 se não
     * é do tenant (IDOR), 422 se já fechada/cancelada.
     */
    private function resolveOpenComanda(int $tenantId, string $comandaUuid, bool $lockForUpdate = false): Comanda
    {
        $query = Comanda::where('uuid', $comandaUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $comanda = $query->first();

        if ($comanda === null) {
            abort(404);
        }

        if (!$comanda->isOpen()) {
            throw new ComandaException(__('messages.comanda.not_open'));
        }

        return $comanda;
    }

    /**
     * Cliente é conceitualmente inexistente na comanda de balcão; para não
     * tornar orders.client_id nullable, a venda é atribuída a um "Consumidor
     * Final" resolvido-ou-criado por tenant (mesmo padrão do PDV).
     */
    private function resolveWalkInClientUuid(int $tenantId): string
    {
        $walkIn = Client::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => self::WALK_IN_CLIENT_NAME,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
            ]
        );

        return $walkIn->uuid;
    }

    /**
     * Fila viva de uma estação (tickets do KDS): itens já enviados e ainda não
     * entregues/cancelados daquela estação, ordenados por tempo de espera
     * (mais antigo primeiro). 404 se a estação não é do tenant.
     */
    public function stationTickets(int $tenantId, string $stationUuid): Collection
    {
        $station = Station::where('uuid', $stationUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if ($station === null) {
            abort(404);
        }

        return ComandaItem::where('comanda_items.tenant_id', $tenantId)
            ->where('station_id', $station->id)
            ->whereIn('prep_status', ComandaItem::ACTIVE_STATION_STATUSES)
            ->whereNull('comanda_items.deleted_at')
            ->with(['product', 'comanda.table'])
            ->orderBy('sent_to_station_at')
            ->get();
    }
}
