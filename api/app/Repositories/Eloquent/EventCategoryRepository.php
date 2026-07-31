<?php

namespace App\Repositories\Eloquent;

use App\Models\Event\EventCategory;
use App\Repositories\Contracts\EventCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class EventCategoryRepository extends BaseRepository implements EventCategoryRepositoryInterface
{
    public function __construct(EventCategory $model)
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
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }
}
