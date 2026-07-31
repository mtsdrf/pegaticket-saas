<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Collection;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    public function __construct(Tenant $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Tenant
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Tenant
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveTenants(): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdBySlug(string $slug): ?int
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('slug', $slug)
            ->value('id');
    }

    public function getIdByUuid(string $uuid): ?int
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('uuid', $uuid)
            ->value('id');
    }

    /**
     * {@inheritdoc}
     */
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
