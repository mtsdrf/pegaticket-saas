<?php

namespace App\Repositories\Eloquent;

use App\Models\Client\PeriodoIdeal;
use App\Repositories\Contracts\PeriodoIdealRepositoryInterface;
use Illuminate\Support\Collection;

class PeriodoIdealRepository extends BaseRepository implements PeriodoIdealRepositoryInterface
{
    public function __construct(PeriodoIdeal $model)
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

    public function getActivePeriodosIdeais(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
