<?php

namespace App\Repositories\Eloquent;

use App\Models\Product\ProductType;
use App\Repositories\Contracts\ProductTypeRepositoryInterface;
use Illuminate\Support\Collection;

class ProductTypeRepository extends BaseRepository implements ProductTypeRepositoryInterface
{
    public function __construct(ProductType $model)
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

    public function getActiveTypes(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }
}
