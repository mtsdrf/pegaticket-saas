<?php

namespace App\Repositories\Eloquent;

use App\Models\Client\DiaIdeal;
use App\Repositories\Contracts\DiaIdealRepositoryInterface;
use Illuminate\Support\Collection;

class DiaIdealRepository extends BaseRepository implements DiaIdealRepositoryInterface
{
    public function __construct(DiaIdeal $model)
    {
        parent::__construct($model);
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

    public function getActiveDiasIdeais(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
