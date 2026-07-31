<?php

namespace App\Repositories\Eloquent;

use App\Models\Client\ClientCategory;
use App\Repositories\Contracts\ClientCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class ClientCategoryRepository extends BaseRepository implements ClientCategoryRepositoryInterface
{
    public function __construct(ClientCategory $model)
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

    public function getActiveCategories(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
