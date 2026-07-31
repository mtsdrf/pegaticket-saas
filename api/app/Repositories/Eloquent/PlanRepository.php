<?php

namespace App\Repositories\Eloquent;

use App\Models\Plan\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Support\Collection;

class PlanRepository extends BaseRepository implements PlanRepositoryInterface
{
    public function __construct(Plan $model)
    {
        parent::__construct($model);
    }

    public function findBySlug(string $slug): ?Plan
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->first();
    }

    public function getActivePlans(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getIdBySlug(string $slug): ?int
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->value('id');
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
