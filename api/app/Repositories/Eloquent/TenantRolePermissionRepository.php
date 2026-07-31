<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantRolePermission;
use App\Repositories\Contracts\TenantRolePermissionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class TenantRolePermissionRepository extends BaseRepository implements TenantRolePermissionRepositoryInterface
{
    public function __construct(TenantRolePermission $model)
    {
        parent::__construct($model);
    }

    public function getRolePermissions(int $tenantRoleId): Collection
    {
        return DB::table('tenant_role_permissions as trp')
            ->join('functionalities as f', 'f.id', '=', 'trp.functionality_id')
            ->join('actions as a', 'a.id', '=', 'trp.action_id')
            ->where('trp.tenant_role_id', $tenantRoleId)
            ->whereNull('trp.deleted_at')
            ->select('f.slug as functionality', 'a.key as action', 'trp.discount_limit_percent')
            ->get();
    }

    public function syncPermissions(int $tenantRoleId, array $permissions): void
    {
        DB::table('tenant_role_permissions')
            ->where('tenant_role_id', $tenantRoleId)
            ->delete();

        foreach ($permissions as $perm) {

            $funcId = DB::table('functionalities')
                ->where('slug', $perm['functionality'])
                ->value('id');

            $actionId = DB::table('actions')
                ->where('key', $perm['action'])
                ->value('id');

            if (!$funcId || !$actionId) {
                continue;
            }

            TenantRolePermission::create([
                'tenant_role_id' => $tenantRoleId,
                'functionality_id' => $funcId,
                'action_id' => $actionId,
                'discount_limit_percent' => $perm['discount_limit_percent'] ?? null,
            ]);
        }
    }
}