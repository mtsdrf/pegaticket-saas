<?php

namespace App\Repositories\Eloquent;

use App\Models\Balcao\Station;
use App\Repositories\Contracts\StationRepositoryInterface;
use Illuminate\Support\Collection;

class StationRepository extends BaseRepository implements StationRepositoryInterface
{
    public function __construct(Station $model)
    {
        parent::__construct($model);
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function findByUuidForTenant(string $uuid, int $tenantId): ?Station
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function nameExists(int $tenantId, string $name, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
