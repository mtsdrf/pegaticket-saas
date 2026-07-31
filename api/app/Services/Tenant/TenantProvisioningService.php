<?php

namespace App\Services\Tenant;

use App\Models\Functionality\Functionality;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantRole;
use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function createOwnerRole(Tenant $tenant): TenantRole
    {
        $ownerRole = TenantRole::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'owner')
            ->first();

        if (!$ownerRole) {
            return TenantRole::create([
                'tenant_id' => $tenant->id,
                'name' => 'Proprietário',
                'slug' => 'owner',
                'is_active' => true,
            ]);
        }

        if ($ownerRole->trashed()) {
            $ownerRole->restore();
        }

        $ownerRole->fill([
            'name' => 'Proprietário',
            'is_active' => true,
        ]);
        $ownerRole->save();

        return $ownerRole->fresh();
    }

    public function attachOwnerUser(Tenant $tenant, int $userId, TenantRole $ownerRole): TenantUser
    {
        $tenantUser = TenantUser::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->first();

        if (!$tenantUser) {
            return TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'tenant_role_id' => $ownerRole->id,
                'is_active' => true,
            ]);
        }

        if ($tenantUser->trashed()) {
            $tenantUser->restore();
        }

        $tenantUser->fill([
            'tenant_role_id' => $ownerRole->id,
            'is_active' => true,
        ]);
        $tenantUser->save();

        return $tenantUser->fresh();
    }

    public function syncOwnerRolePermissions(Tenant $tenant, TenantRole $ownerRole): void
    {
        $functionalityIds = $this->resolveAllowedFunctionalityIds($tenant);
        $actionIds = DB::table('actions')->pluck('id')->all();

        $desiredKeys = [];

        foreach ($functionalityIds as $functionalityId) {
            foreach ($actionIds as $actionId) {
                $desiredKeys["{$functionalityId}:{$actionId}"] = [
                    'functionality_id' => (int) $functionalityId,
                    'action_id' => (int) $actionId,
                ];
            }
        }

        $existing = DB::table('tenant_role_permissions')
            ->select(['id', 'functionality_id', 'action_id'])
            ->where('tenant_role_id', $ownerRole->id)
            ->get();

        $existingKeys = [];
        $existingIdsByKey = [];

        foreach ($existing as $row) {
            $key = "{$row->functionality_id}:{$row->action_id}";
            $existingKeys[$key] = true;
            $existingIdsByKey[$key] = (int) $row->id;
        }

        $toDelete = [];
        foreach (array_keys($existingKeys) as $key) {
            if (!isset($desiredKeys[$key])) {
                $toDelete[] = $existingIdsByKey[$key];
            }
        }

        if ($toDelete !== []) {
            DB::table('tenant_role_permissions')
                ->whereIn('id', $toDelete)
                ->delete();
        }

        $toInsert = [];
        foreach ($desiredKeys as $key => $permission) {
            if (isset($existingKeys[$key])) {
                continue;
            }

            $toInsert[] = [
                'uuid' => (string) Str::uuid(),
                'tenant_role_id' => $ownerRole->id,
                'functionality_id' => $permission['functionality_id'],
                'action_id' => $permission['action_id'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'deleted_by' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($toInsert !== []) {
            DB::table('tenant_role_permissions')->insert($toInsert);
        }
    }

    /**
     * @return list<int>
     */
    private function resolveAllowedFunctionalityIds(Tenant $tenant): array
    {
        $query = Functionality::query()
            ->whereNull('deleted_at')
            ->where('is_active', true);

        if ($tenant->plan_id) {
            $query->whereExists(function ($subQuery) use ($tenant) {
                $subQuery->selectRaw('1')
                    ->from('plan_functionalities')
                    ->whereColumn('plan_functionalities.functionality_id', 'functionalities.id')
                    ->where('plan_functionalities.plan_id', $tenant->plan_id)
                    ->whereNull('plan_functionalities.deleted_at');
            });
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
