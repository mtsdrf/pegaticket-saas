<?php

namespace App\Repositories\Eloquent;

use App\Models\Balcao\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Collection;

class TableRepository extends BaseRepository implements TableRepositoryInterface
{
    public function __construct(Table $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderBy('label')
            ->get();
    }

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Table
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function labelExists(int $tenantId, string $label, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('label', $label);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
