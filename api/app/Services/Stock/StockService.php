<?php

namespace App\Services\Stock;

use App\Events\Stock\StockMovementCreated;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockMovementException;
use App\Models\BaseModel;
use App\Models\Product\Product;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use App\Models\Stock\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lógica central do módulo de Estoque. Cada método público roda dentro de
 * DB::transaction() com lockForUpdate() na(s) linha(s) de StockBalance
 * envolvida(s) antes de ler o saldo atual, para evitar race condition em
 * movimentações concorrentes no mesmo produto+local.
 *
 * balance_before/balance_after gravados em cada StockMovement sempre se
 * referem ao campo relevante para aquele tipo (on_hand, reserved ou
 * blocked) — ver PHPDoc de StockMovement.
 */
class StockService
{
    public function __construct(
        private StockMovementRepositoryInterface $movementRepository
    ) {
    }

    public function paginateBalances(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'product_name' => 'products.name',
            'location_name' => 'stock_locations.name',
            'quantity_on_hand' => 'stock_balances.quantity_on_hand',
            'quantity_reserved' => 'stock_balances.quantity_reserved',
            'quantity_blocked' => 'stock_balances.quantity_blocked',
            'quantity_available' => 'quantity_available',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $needsJoin = in_array($sortColumn, ['products.name', 'stock_locations.name'], true)
            || !empty($filters['product_name'])
            || !empty($filters['location_name']);

        $query = StockBalance::query()
            ->select([
                'stock_balances.*',
                DB::raw('(stock_balances.quantity_on_hand - stock_balances.quantity_reserved - stock_balances.quantity_blocked) as quantity_available'),
            ])
            ->where('stock_balances.tenant_id', $tenantId)
            ->whereNull('stock_balances.deleted_at')
            ->with(['product', 'location']);

        if ($needsJoin) {
            $query->leftJoin('products', 'products.id', '=', 'stock_balances.product_id')
                ->leftJoin('stock_locations', 'stock_locations.id', '=', 'stock_balances.location_id');
        }

        if (!empty($filters['product_uuid'])) {
            $query->whereHas('product', fn($q) => $q->where('uuid', $filters['product_uuid']));
        }

        if (!empty($filters['location_uuid'])) {
            $query->whereHas('location', fn($q) => $q->where('uuid', $filters['location_uuid']));
        }

        if (!empty($filters['product_name'])) {
            $query->where('products.name', 'like', '%' . $filters['product_name'] . '%');
        }

        if (!empty($filters['location_name'])) {
            $query->where('stock_locations.name', 'like', '%' . $filters['location_name'] . '%');
        }

        $this->applyDecimalRangeFilter($query, $filters, 'stock_balances.quantity_on_hand', 'quantity_on_hand');
        $this->applyDecimalRangeFilter($query, $filters, 'stock_balances.quantity_reserved', 'quantity_reserved');
        $this->applyDecimalRangeFilter($query, $filters, 'stock_balances.quantity_blocked', 'quantity_blocked');
        $this->applyDecimalRangeFilter(
            $query,
            $filters,
            '(stock_balances.quantity_on_hand - stock_balances.quantity_reserved - stock_balances.quantity_blocked)',
            'quantity_available',
            true
        );

        if ($sortColumn === 'quantity_available') {
            $query->orderByRaw('(stock_balances.quantity_on_hand - stock_balances.quantity_reserved - stock_balances.quantity_blocked) ' . GridQuery::normalizeSortDir($sortDir));
        } else {
            $query->orderBy($sortColumn ?? 'stock_balances.id', GridQuery::normalizeSortDir($sortDir));
        }

        return $query->paginate($perPage);
    }

    public function paginateMovements(
        int $tenantId,
        array $filters = [],
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'type' => 'stock_movements.type',
            'product_name' => 'products.name',
            'location_name' => 'stock_locations.name',
            'quantity' => 'stock_movements.quantity',
            'reason' => 'stock_movements.reason',
            'balance_after' => 'stock_movements.balance_after',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $needsJoin = in_array($sortColumn, ['products.name', 'stock_locations.name'], true)
            || !empty($filters['product_name'])
            || !empty($filters['location_name']);

        $query = StockMovement::query()
            ->select(['stock_movements.*'])
            ->where('stock_movements.tenant_id', $tenantId)
            ->whereNull('stock_movements.deleted_at')
            ->with(['product', 'location', 'destinationLocation']);

        if ($needsJoin) {
            $query->leftJoin('products', 'products.id', '=', 'stock_movements.product_id')
                ->leftJoin('stock_locations', 'stock_locations.id', '=', 'stock_movements.location_id');
        }

        if (!empty($filters['product_uuid'])) {
            $query->whereHas('product', fn($q) => $q->where('uuid', $filters['product_uuid']));
        }

        if (!empty($filters['location_uuid'])) {
            $query->whereHas('location', fn($q) => $q->where('uuid', $filters['location_uuid']));
        }

        if (!empty($filters['type'])) {
            $query->where('stock_movements.type', $filters['type']);
        }

        if (!empty($filters['product_name'])) {
            $query->where('products.name', 'like', '%' . $filters['product_name'] . '%');
        }

        if (!empty($filters['location_name'])) {
            $query->where('stock_locations.name', 'like', '%' . $filters['location_name'] . '%');
        }

        if (!empty($filters['reason'])) {
            $query->where('stock_movements.reason', 'like', '%' . $filters['reason'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('stock_movements.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('stock_movements.created_at', '<=', $filters['date_to']);
        }

        $this->applyDecimalRangeFilter($query, $filters, 'stock_movements.quantity', 'quantity');
        $this->applyDecimalRangeFilter($query, $filters, 'stock_movements.balance_after', 'balance_after');

        $query->orderBy($sortColumn ?? 'stock_movements.id', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    private function applyDecimalRangeFilter(Builder $query, array $filters, string $column, string $filterKey, bool $raw = false): void
    {
        $minKey = $filterKey . '_min';
        $maxKey = $filterKey . '_max';

        if (array_key_exists($minKey, $filters) && $filters[$minKey] !== null && $filters[$minKey] !== '') {
            $raw
                ? $query->whereRaw($column . ' >= ?', [$filters[$minKey]])
                : $query->where($column, '>=', $filters[$minKey]);
        }

        if (array_key_exists($maxKey, $filters) && $filters[$maxKey] !== null && $filters[$maxKey] !== '') {
            $raw
                ? $query->whereRaw($column . ' <= ?', [$filters[$maxKey]])
                : $query->where($column, '<=', $filters[$maxKey]);
        }
    }

    public function entry(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null, ?float $unitCost = null): StockMovement
    {
        return $this->creditOnHand($product, $location, $quantity, 'entry', $reason, $notes, $unitCost);
    }

    /**
     * $sourceType/$sourceId (opcionais) apontam para a origem da saída —
     * usado pelo Balcão (Fase 1) para vincular a baixa real de estoque ao
     * ComandaItem que a gerou no envio à estação, mesmo mecanismo polimórfico
     * já usado por reserve().
     */
    public function exit(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null, ?string $sourceType = null, ?int $sourceId = null): StockMovement
    {
        return $this->debitOnHand($product, $location, $quantity, 'exit', $reason, $notes, $sourceType, $sourceId);
    }

    public function returnStock(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null): StockMovement
    {
        return $this->creditOnHand($product, $location, $quantity, 'return', $reason, $notes);
    }

    public function loss(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null): StockMovement
    {
        return $this->debitOnHand($product, $location, $quantity, 'loss', $reason, $notes);
    }

    public function adjust(Product $product, StockLocation $location, float $quantity, string $direction, string $reason, ?string $notes = null): StockMovement
    {
        return match ($direction) {
            'increase' => $this->creditOnHand($product, $location, $quantity, 'adjustment_positive', $reason, $notes),
            'decrease' => $this->debitOnHand($product, $location, $quantity, 'adjustment_negative', $reason, $notes),
            default => throw new \InvalidArgumentException("Invalid adjustment direction: {$direction}"),
        };
    }

    public function transfer(Product $product, StockLocation $from, StockLocation $to, float $quantity, string $reason, ?string $notes = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($from);
        $this->assertTenantOwnership($to);

        if ($from->id === $to->id) {
            throw new InvalidStockMovementException(__('messages.stock.transfer_same_location'));
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $reason, $notes) {
            [$fromBalance, $toBalance] = $this->lockTransferBalances($product, $from, $to);

            if ($quantity > $fromBalance->quantity_available) {
                throw new InsufficientStockException(__('messages.stock.insufficient_balance'));
            }

            $before = (float) $fromBalance->quantity_on_hand;
            $fromBalance->quantity_on_hand = $before - $quantity;
            $fromBalance->save();

            $toBalance->quantity_on_hand = (float) $toBalance->quantity_on_hand + $quantity;
            $toBalance->save();

            return $this->recordMovement(
                product: $product,
                location: $from,
                type: 'transfer',
                quantity: $quantity,
                before: $before,
                after: (float) $fromBalance->quantity_on_hand,
                reason: $reason,
                notes: $notes,
                destinationLocation: $to,
            );
        });
    }

    public function block(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($location);

        return DB::transaction(function () use ($product, $location, $quantity, $reason, $notes) {
            $balance = $this->lockOrCreateBalance($product, $location);

            if ($quantity > $balance->quantity_available) {
                throw new InsufficientStockException(__('messages.stock.insufficient_balance'));
            }

            $before = (float) $balance->quantity_blocked;
            $balance->quantity_blocked = $before + $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: 'block',
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_blocked,
                reason: $reason,
                notes: $notes,
            );
        });
    }

    public function unblock(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($location);

        return DB::transaction(function () use ($product, $location, $quantity, $reason, $notes) {
            $balance = $this->lockOrCreateBalance($product, $location);

            if ($quantity > (float) $balance->quantity_blocked) {
                throw new InsufficientStockException(__('messages.stock.insufficient_blocked_balance'));
            }

            $before = (float) $balance->quantity_blocked;
            $balance->quantity_blocked = $before - $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: 'unblock',
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_blocked,
                reason: $reason,
                notes: $notes,
            );
        });
    }

    /**
     * $sourceType/$sourceId (opcionais) apontam para a origem da reserva —
     * usado pelo Pedido (Fase 5) para marcar o OrderItem que gerou esta
     * reserva, reaproveitando a mesma coluna polimórfica já usada por
     * reserve_cancel para apontar para o reserve original.
     */
    public function reserve(Product $product, StockLocation $location, float $quantity, string $reason, ?string $notes = null, ?string $sourceType = null, ?int $sourceId = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($location);

        return DB::transaction(function () use ($product, $location, $quantity, $reason, $notes, $sourceType, $sourceId) {
            $balance = $this->lockOrCreateBalance($product, $location);

            if ($quantity > (float) $balance->quantity_available) {
                throw new InsufficientStockException(__('messages.stock.insufficient_balance'));
            }

            $before = (float) $balance->quantity_reserved;
            $balance->quantity_reserved = $before + $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: 'reserve',
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_reserved,
                reason: $reason,
                notes: $notes,
                sourceType: $sourceType,
                sourceId: $sourceId,
            );
        });
    }

    /**
     * Cancela uma reserva ainda não convertida em saída real. Reaproveita
     * a mesma quantidade e o mesmo motivo do movimento `reserve` original,
     * e aponta `source_type`/`source_id` para ele (coluna polimórfica que
     * já existia reservada para o Pedido apontar aqui na Fase 5).
     */
    public function reserveCancel(StockMovement $originalReserve, ?string $notes = null): StockMovement
    {
        $this->assertTenantOwnership($originalReserve);

        if ($originalReserve->type !== 'reserve') {
            throw new InvalidStockMovementException(__('messages.stock.invalid_reserve_movement'));
        }

        return DB::transaction(function () use ($originalReserve, $notes) {
            $product = Product::findOrFail($originalReserve->product_id);
            $location = StockLocation::findOrFail($originalReserve->location_id);

            // A checagem de "já cancelado" só é segura DEPOIS do lock do
            // saldo: um lockForUpdate() numa query que não bate nenhuma
            // linha (caso comum aqui, já que normalmente só existe 0 ou 1
            // reserve_cancel) não trava nada e não serializa duas chamadas
            // concorrentes. lockOrCreateBalance() já trava a linha de
            // StockBalance do par produto+local — como as duas transações
            // concorrentes de reserveCancel() pra mesma reserva original
            // sempre disputam essa mesma linha, reaproveitamos esse lock
            // como ponto de serialização em vez de confiar no lock da
            // checagem isolada.
            $balance = $this->lockOrCreateBalance($product, $location);

            $alreadyCancelled = StockMovement::where('source_type', StockMovement::class)
                ->where('source_id', $originalReserve->id)
                ->where('type', 'reserve_cancel')
                ->exists();

            if ($alreadyCancelled) {
                throw new InvalidStockMovementException(__('messages.stock.reserve_already_cancelled'));
            }

            $quantity = (float) $originalReserve->quantity;

            if ($quantity > (float) $balance->quantity_reserved) {
                throw new InsufficientStockException(__('messages.stock.insufficient_reserved_balance'));
            }

            $before = (float) $balance->quantity_reserved;
            $balance->quantity_reserved = $before - $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: 'reserve_cancel',
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_reserved,
                reason: $originalReserve->reason,
                notes: $notes,
                sourceType: StockMovement::class,
                sourceId: $originalReserve->id,
            );
        });
    }

    private function creditOnHand(Product $product, StockLocation $location, float $quantity, string $type, string $reason, ?string $notes, ?float $unitCost = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($location);

        return DB::transaction(function () use ($product, $location, $quantity, $type, $reason, $notes, $unitCost) {
            $balance = $this->lockOrCreateBalance($product, $location);

            $before = (float) $balance->quantity_on_hand;
            $balance->quantity_on_hand = $before + $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: $type,
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_on_hand,
                reason: $reason,
                notes: $notes,
                unitCost: $unitCost,
            );
        });
    }

    private function debitOnHand(Product $product, StockLocation $location, float $quantity, string $type, string $reason, ?string $notes, ?string $sourceType = null, ?int $sourceId = null): StockMovement
    {
        $this->assertTenantOwnership($product);
        $this->assertTenantOwnership($location);

        return DB::transaction(function () use ($product, $location, $quantity, $type, $reason, $notes, $sourceType, $sourceId) {
            $balance = $this->lockOrCreateBalance($product, $location);

            if ($quantity > $balance->quantity_available) {
                throw new InsufficientStockException(__('messages.stock.insufficient_balance'));
            }

            $before = (float) $balance->quantity_on_hand;
            $balance->quantity_on_hand = $before - $quantity;
            $balance->save();

            return $this->recordMovement(
                product: $product,
                location: $location,
                type: $type,
                quantity: $quantity,
                before: $before,
                after: (float) $balance->quantity_on_hand,
                reason: $reason,
                notes: $notes,
                sourceType: $sourceType,
                sourceId: $sourceId,
            );
        });
    }

    /**
     * Busca (com lockForUpdate) o StockBalance do par produto+local. Cria
     * lazy com zeros na primeira movimentação que tocar o par. Se duas
     * transações concorrentes tentarem criar o mesmo par pela primeira
     * vez ao mesmo tempo, a unique (product_id,location_id) rejeita a
     * segunda — recuperamos reconsultando com lock, já que a primeira já
     * deve ter comprometido a linha a essa altura.
     */
    private function lockOrCreateBalance(Product $product, StockLocation $location): StockBalance
    {
        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        try {
            StockBalance::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_blocked' => 0,
            ]);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = violação de constraint (padrão ANSI, vale
            // tanto para MySQL/MariaDB quanto SQLite) — é a corrida
            // esperada de outra transação criando o mesmo saldo entre o
            // SELECT e o INSERT acima. Qualquer outro código é um erro
            // real e deve propagar, não ser mascarado como corrida.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Locka (e cria se necessário) os dois StockBalance envolvidos numa
     * transferência sempre em ordem crescente de location_id, independente
     * de qual é origem/destino — evita deadlock se duas transferências
     * cruzadas (A->B e B->A) do mesmo produto acontecerem ao mesmo tempo.
     */
    private function lockTransferBalances(Product $product, StockLocation $from, StockLocation $to): array
    {
        $firstLoc = $from->id <= $to->id ? $from : $to;
        $secondLoc = $from->id <= $to->id ? $to : $from;

        $firstBalance = $this->lockOrCreateBalance($product, $firstLoc);
        $secondBalance = $this->lockOrCreateBalance($product, $secondLoc);

        return $firstLoc->id === $from->id
            ? [$firstBalance, $secondBalance]
            : [$secondBalance, $firstBalance];
    }

    private function recordMovement(
        Product $product,
        StockLocation $location,
        string $type,
        float $quantity,
        float $before,
        float $after,
        string $reason,
        ?string $notes,
        ?StockLocation $destinationLocation = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?float $unitCost = null
    ): StockMovement {
        $movement = $this->movementRepository->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'destination_location_id' => $destinationLocation?->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_before' => $before,
            'balance_after' => $after,
            'reason' => $reason,
            'notes' => $notes,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        event(new StockMovementCreated(
            stockMovementUuid: $movement->uuid,
            type: $type,
            productUuid: $product->uuid,
            locationUuid: $location->uuid,
            quantity: $quantity,
            actorId: Auth::id()
        ));

        return $movement;
    }

    /**
     * Todo Product/StockLocation/StockMovement recebido precisa ser
     * confirmado como do tenant atual antes de qualquer coisa — mesmo
     * cuidado de IDOR já corrigido antes em Client/Product/TenantRole.
     */
    private function assertTenantOwnership(BaseModel $model): void
    {
        if ((int) $model->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
