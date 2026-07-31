<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use Illuminate\Support\Collection;

class TenantUserRepository extends BaseRepository implements TenantUserRepositoryInterface
{
    public function __construct(TenantUser $model)
    {
        parent::__construct($model);
    }

    public function getUserTenants(int $userId): Collection
    {
        return $this->model
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->leftJoin('plans', 'plans.id', '=', 'tenants.plan_id')
            ->leftJoin('tenant_settings', function ($join) {
                $join->on('tenant_settings.tenant_id', '=', 'tenants.id')
                    ->whereNull('tenant_settings.deleted_at');
            })
            ->where('tenant_users.user_id', $userId)
            ->whereNull('tenant_users.deleted_at')
            ->where('tenant_users.is_active', true)
            ->select(
                'tenants.uuid as tenant_uuid',
                'tenants.name as tenant_name',
                'tenants.logo_path as logo_path',
                'tenants.logo_mime as logo_mime',
                'tenants.logo_updated_at as logo_updated_at',
                'tenant_roles.name as role',
                'plans.slug as plan_slug',
                'plans.name as plan_name'
            )
            ->selectRaw('COALESCE(tenant_settings.send_tracking_link_whatsapp, 0) as send_tracking_link_whatsapp')
            ->get();
    }

    public function findByTenantAndUser(int $tenantId, int $userId): ?TenantUser
    {
        return $this->model
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findOwnerUserForTenant(int $tenantId): ?User
    {
        return User::query()
            ->join('tenant_users', 'tenant_users.user_id', '=', 'users.id')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->where('tenant_users.is_active', true)
            ->where('tenant_roles.slug', 'owner')
            ->where('tenant_roles.is_active', true)
            ->whereNull('tenant_users.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->whereNull('users.deleted_at')
            ->select('users.*')
            ->first();
    }
}
