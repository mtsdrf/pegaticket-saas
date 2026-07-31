<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant\TenantRole;
use Illuminate\Support\Collection;

interface TenantRoleRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySlug(int $tenantId, string $slug): ?TenantRole;
    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool;
    public function getActiveRoles(int $tenantId): Collection;
}