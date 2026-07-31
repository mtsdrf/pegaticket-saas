<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantRole;
use App\Repositories\Contracts\TenantRoleRepositoryInterface;
use Illuminate\Support\Collection;

class TenantRoleRepository extends BaseRepository implements TenantRoleRepositoryInterface
{
    public function __construct(TenantRole $model)
    {
        parent::__construct($model);
    }

    public function findBySlug(int $tenantId, string $slug): ?TenantRole
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getActiveRoles(int $tenantId): Collection
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}