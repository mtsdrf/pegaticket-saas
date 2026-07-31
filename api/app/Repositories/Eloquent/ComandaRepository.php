<?php

namespace App\Repositories\Eloquent;

use App\Models\Balcao\Comanda;
use App\Repositories\Contracts\ComandaRepositoryInterface;
use Illuminate\Support\Collection;

class ComandaRepository extends BaseRepository implements ComandaRepositoryInterface
{
    public function __construct(Comanda $model)
    {
        parent::__construct($model);
    }

    public function listOpenForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Comanda::STATUS_OPEN, Comanda::STATUS_CLOSING])
            ->with(['table', 'items.product', 'items.station'])
            ->orderByDesc('id')
            ->get();
    }

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Comanda
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->with(['table', 'items.product', 'items.station'])
            ->first();
    }

    public function hasOtherOpenComandaOnTable(int $tableId, int $excludeComandaId): bool
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('table_id', $tableId)
            ->where('id', '!=', $excludeComandaId)
            ->whereIn('status', [Comanda::STATUS_OPEN, Comanda::STATUS_CLOSING])
            ->exists();
    }
}
