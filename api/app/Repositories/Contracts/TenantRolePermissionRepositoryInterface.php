<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TenantRolePermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function syncPermissions(int $tenantRoleId, array $permissions): void;
    public function getRolePermissions(int $tenantRoleId): Collection;
}