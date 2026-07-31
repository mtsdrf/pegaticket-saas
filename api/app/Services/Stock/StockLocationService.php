<?php

namespace App\Services\Stock;

use App\DTOs\Stock\CreateStockLocationDTO;
use App\DTOs\Stock\UpdateStockLocationDTO;
use App\Events\Stock\StockLocationCreated;
use App\Events\Stock\StockLocationUpdated;
use App\Events\Stock\StockLocationDeleted;
use App\Exceptions\DuplicateNameException;
use App\Models\Stock\StockLocation;
use App\Repositories\Contracts\StockLocationRepositoryInterface;
use App\Support\GridQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockLocationService
{
    public function __construct(
        private StockLocationRepositoryInterface $repository
    ) {
    }

    public function find(StockLocation $stockLocation): StockLocation
    {
        $this->assertBelongsToCurrentTenant($stockLocation);

        return $stockLocation;
    }

    public function paginate(
        int $tenantId,
        int $perPage = 15,
        array $filters = [],
        ?string $sortBy = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'name' => 'stock_locations.name',
            'type' => 'stock_locations.type',
            'address' => 'stock_locations.address',
            'is_default' => 'stock_locations.is_default',
            'is_active' => 'stock_locations.is_active',
        ];

        $query = StockLocation::query()
            ->select([
                'stock_locations.id',
                'stock_locations.uuid',
                'stock_locations.name',
                'stock_locations.type',
                'stock_locations.address',
                'stock_locations.is_default',
                'stock_locations.is_active',
                'stock_locations.created_at',
            ])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ;

        GridQuery::applyTextFilters($query, $filters, [
            'name' => 'stock_locations.name',
            'type' => 'stock_locations.type',
            'address' => 'stock_locations.address',
        ]);

        GridQuery::applyBooleanFilters($query, $filters, [
            'is_default' => 'stock_locations.is_default',
            'is_active' => 'stock_locations.is_active',
        ]);

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;
        $query->orderBy($sortColumn ?? 'stock_locations.name', GridQuery::normalizeSortDir($sortDir));

        return $query->paginate($perPage);
    }

    public function create(CreateStockLocationDTO $dto): StockLocation
    {
        return DB::transaction(function () use ($dto) {

            if ($this->repository->nameExists($dto->tenantId, $dto->name)) {
                throw new DuplicateNameException(__('messages.stock_location.name_exists'));
            }

            if ($dto->isDefault) {
                StockLocation::where('tenant_id', $dto->tenantId)
                    ->update(['is_default' => false]);
            }

            $location = $this->repository->create([
                'tenant_id' => $dto->tenantId,
                'name' => $dto->name,
                'type' => $dto->type,
                'address' => $dto->address,
                'is_default' => $dto->isDefault,
                'is_active' => $dto->isActive,
            ]);

            event(new StockLocationCreated(
                stockLocationUuid: $location->uuid,
                actorId: Auth::id()
            ));

            return $location;
        });
    }

    public function update(StockLocation $stockLocation, UpdateStockLocationDTO $dto): StockLocation
    {
        $this->assertBelongsToCurrentTenant($stockLocation);

        return DB::transaction(function () use ($stockLocation, $dto) {

            $original = $stockLocation->getOriginal();

            if ($dto->name && $this->repository->nameExists($stockLocation->tenant_id, $dto->name, $stockLocation->id)) {
                throw new DuplicateNameException(__('messages.stock_location.name_exists'));
            }

            if ($dto->isDefault) {
                StockLocation::where('tenant_id', $stockLocation->tenant_id)
                    ->where('id', '!=', $stockLocation->id)
                    ->update(['is_default' => false]);
            }

            $data = array_filter([
                'name' => $dto->name,
                'type' => $dto->type,
                'address' => $dto->address,
                'is_default' => $dto->isDefault,
                'is_active' => $dto->isActive,
            ], fn($v) => !is_null($v));

            if (!empty($data)) {
                $stockLocation = $this->repository->update($stockLocation, $data);

                $changes = array_diff_assoc($stockLocation->getAttributes(), $original);

                if (!empty($changes)) {
                    event(new StockLocationUpdated(
                        stockLocationUuid: $stockLocation->uuid,
                        actorId: Auth::id(),
                        changes: array_keys($changes)
                    ));
                }
            }

            return $stockLocation;
        });
    }

    public function delete(StockLocation $stockLocation): void
    {
        $this->assertBelongsToCurrentTenant($stockLocation);

        DB::transaction(function () use ($stockLocation) {
            $this->repository->delete($stockLocation);

            event(new StockLocationDeleted(
                stockLocationUuid: $stockLocation->uuid,
                actorId: Auth::id()
            ));
        });
    }

    /**
     * Idempotente: cria a location padrão "Depósito Principal" só se o
     * tenant ainda não tiver nenhuma StockLocation. Chamado pelo listener
     * de TenantCreated (CreateDefaultStockLocation) — usuário não precisa
     * configurar um local antes de conseguir movimentar estoque.
     */
    public function ensureDefaultLocation(int $tenantId): StockLocation
    {
        $existing = StockLocation::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->repository->create([
            'tenant_id' => $tenantId,
            'name' => 'Depósito Principal',
            'type' => 'deposito',
            'address' => null,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — sem
     * esta checagem, um usuário com permissão poderia mutar registro de
     * outro tenant só sabendo o uuid (IDOR).
     */
    private function assertBelongsToCurrentTenant(StockLocation $stockLocation): void
    {
        if ((int) $stockLocation->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
