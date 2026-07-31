<?php

namespace App\Repositories\Eloquent;

use App\Models\Stock\StockLocation;
use App\Repositories\Contracts\StockLocationRepositoryInterface;

class StockLocationRepository extends BaseRepository implements StockLocationRepositoryInterface
{
    public function __construct(StockLocation $model)
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
}
