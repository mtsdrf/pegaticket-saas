<?php

namespace App\Services\Permission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function getAccessProfile(int $userId, ?int $tenantId = null, ?string $tenantUuid = null): array
    {
        $globalPermissions = $this->resolveGroupPermissionSet($userId);
        $tenantPermissions = [];
        $tenantFunctionalities = [];
        $isTenantOwner = false;

        if ($tenantId) {
            $tenantPermissions = $this->resolveTenantPermissionSet($userId, $tenantId, $globalPermissions);
            $tenantFunctionalities = array_values(array_unique(array_map(
                fn (string $permission) => explode(':', $permission)[0],
                $tenantPermissions
            )));
            sort($tenantFunctionalities);
            $isTenantOwner = $this->userIsTenantOwner($userId, $tenantId);
        }

        return [
            'is_administrator' => $this->userHasGroupSlug($userId, 'administrators'),
            'has_tenant_context' => $tenantId !== null,
            'is_tenant_owner' => $isTenantOwner,
            'tenant_uuid' => $tenantUuid,
            'global_permissions' => array_values($globalPermissions),
            'tenant_permissions' => array_values($tenantPermissions),
            'tenant_functionalities' => $tenantFunctionalities,
        ];
    }

    public function userCanViaGroups(int $userId, string $functionalitySlug, string $actionKey): bool
    {
        $cacheKey = "permset:user:{$userId}";

        $permSet = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId) {
            $rows = DB::table('group_user')
                ->join('group_permissions', 'group_permissions.group_id', '=', 'group_user.group_id')
                ->join('functionalities', 'functionalities.id', '=', 'group_permissions.functionality_id')
                ->join('actions', 'actions.id', '=', 'group_permissions.action_id')
                ->whereNull('group_user.deleted_at')
                ->whereNull('group_permissions.deleted_at')
                ->whereNull('functionalities.deleted_at')
                ->where('group_user.user_id', $userId)
                ->select('functionalities.slug as f', 'actions.key as a')
                ->get();

            $set = [];
            foreach ($rows as $r) {
                $set["{$r->f}:{$r->a}"] = true;
            }
            return $set;
        });

        return !empty($permSet["{$functionalitySlug}:{$actionKey}"]);
    }

    public function userCanViaTenantRole(int $tenantRoleId, string $functionalitySlug, string $actionKey): bool
    {
        $cacheKey = "permset:tenant-role:{$tenantRoleId}";

        $permSet = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($tenantRoleId) {
            $rows = DB::table('tenant_role_permissions')
                ->join('functionalities', 'functionalities.id', '=', 'tenant_role_permissions.functionality_id')
                ->join('actions', 'actions.id', '=', 'tenant_role_permissions.action_id')
                ->where('tenant_role_permissions.tenant_role_id', $tenantRoleId)
                ->whereNull('tenant_role_permissions.deleted_at')
                ->whereNull('functionalities.deleted_at')
                ->select('functionalities.slug as f', 'actions.key as a')
                ->get();

            $set = [];
            foreach ($rows as $r) {
                $set["{$r->f}:{$r->a}"] = true;
            }

            return $set;
        });

        return !empty($permSet["{$functionalitySlug}:{$actionKey}"]);
    }

    /**
     * Limite percentual de desconto do perfil (roadmap A1.5, ver
     * architecture-decisions.md pro mapeamento dos pontos de entrada reais
     * que chamam isto: SaleService::create()/updateItems(), via override
     * manual de items[].unit_price). `discount_limit_percent` é armazenado
     * por LINHA de tenant_role_permissions (tenant_role_id+functionality+
     * action), não por perfil inteiro — lê-se a menor configuração não-nula
     * entre TODAS as linhas do perfil pra functionality 'sales' (defensivo:
     * se o admin configurar em mais de uma action por engano, a mais
     * restritiva vence). null = sem limite configurado = sem restrição
     * (comportamento anterior a esta feature, preservado).
     */
    public function resolveSaleDiscountLimitPercent(int $tenantRoleId): ?float
    {
        $limit = DB::table('tenant_role_permissions')
            ->join('functionalities', 'functionalities.id', '=', 'tenant_role_permissions.functionality_id')
            ->where('tenant_role_permissions.tenant_role_id', $tenantRoleId)
            ->where('functionalities.slug', 'sales')
            ->whereNull('tenant_role_permissions.deleted_at')
            ->whereNotNull('tenant_role_permissions.discount_limit_percent')
            ->min('tenant_role_permissions.discount_limit_percent');

        return $limit !== null ? (float) $limit : null;
    }

    public function userHasGroupSlug(int $userId, string $groupSlug): bool
    {
        return DB::table('group_user')
            ->join('groups', 'groups.id', '=', 'group_user.group_id')
            ->where('group_user.user_id', $userId)
            ->where('groups.slug', $groupSlug)
            ->whereNull('group_user.deleted_at')
            ->whereNull('groups.deleted_at')
            ->exists();
    }

    public function userIsTenantOwner(int $userId, int $tenantId): bool
    {
        return DB::table('tenant_users')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->where('tenant_users.user_id', $userId)
            ->where('tenant_users.tenant_id', $tenantId)
            ->where('tenant_users.is_active', true)
            ->where('tenant_roles.is_active', true)
            ->where('tenant_roles.slug', 'owner')
            ->whereNull('tenant_users.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->exists();
    }

    /**
     * Único ponto de decisão "esta functionality está liberada para este
     * tenant" — usado pelo gate de plano em CheckPermission. Delega para
     * resolveTenantAllowedFunctionalities(), que já resolve a precedência
     * override de tenant (tenant_feature_overrides) > plano (roadmap A5,
     * item 19). Mantido como método próprio (não inlined no middleware)
     * por compatibilidade de nome com o resto do código/testes existentes.
     */
    public function tenantPlanAllowsFunctionality(int $tenantId, string $functionalitySlug): bool
    {
        return in_array($functionalitySlug, $this->resolveTenantAllowedFunctionalities($tenantId), true);
    }

    /**
     * True se a assinatura ATUAL do tenant está em estado que bloqueia o
     * acesso operacional (suspended/canceled) — roadmap 1B. O CheckPermission
     * usa isso para negar tudo EXCETO a própria tela de assinatura (para o
     * dono conseguir reativar). Tenant sem nenhuma assinatura NÃO é bloqueado
     * (compatibilidade com a base já existente, que não tem subscription).
     */
    public function tenantSubscriptionBlocksAccess(int $tenantId): bool
    {
        $status = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->value('status');

        return in_array($status, ['suspended', 'canceled'], true);
    }

    public function invalidateUser(int $userId): void
    {
        Cache::forget("permset:user:{$userId}");
    }

    public function invalidateTenantRole(int $tenantRoleId): void
    {
        Cache::forget("permset:tenant-role:{$tenantRoleId}");
    }

    public function invalidateUsersByGroup(int $groupId): void
    {
        $userIds = DB::table('group_user')
            ->where('group_id', $groupId)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        foreach ($userIds as $uid) {
            $this->invalidateUser((int) $uid);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveGroupPermissionSet(int $userId): array
    {
        $rows = DB::table('group_user')
            ->join('group_permissions', 'group_permissions.group_id', '=', 'group_user.group_id')
            ->join('functionalities', 'functionalities.id', '=', 'group_permissions.functionality_id')
            ->join('actions', 'actions.id', '=', 'group_permissions.action_id')
            ->whereNull('group_user.deleted_at')
            ->whereNull('group_permissions.deleted_at')
            ->whereNull('functionalities.deleted_at')
            ->where('group_user.user_id', $userId)
            ->select('functionalities.slug as f', 'actions.key as a')
            ->get();

        $set = [];
        foreach ($rows as $row) {
            $set["{$row->f}:{$row->a}"] = "{$row->f}:{$row->a}";
        }

        ksort($set);

        return array_values($set);
    }

    /**
     * @param list<string> $globalPermissions
     * @return list<string>
     */
    private function resolveTenantPermissionSet(int $userId, int $tenantId, array $globalPermissions): array
    {
        $membership = DB::table('tenant_users')
            ->join('tenant_roles', 'tenant_roles.id', '=', 'tenant_users.tenant_role_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->where('tenant_users.user_id', $userId)
            ->where('tenant_users.is_active', true)
            ->where('tenant_roles.is_active', true)
            ->whereNull('tenant_users.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->select('tenant_roles.id as role_id')
            ->first();

        $tenantRolePermissions = [];
        if ($membership?->role_id) {
            $rows = DB::table('tenant_role_permissions')
                ->join('functionalities', 'functionalities.id', '=', 'tenant_role_permissions.functionality_id')
                ->join('actions', 'actions.id', '=', 'tenant_role_permissions.action_id')
                ->where('tenant_role_permissions.tenant_role_id', $membership->role_id)
                ->whereNull('tenant_role_permissions.deleted_at')
                ->whereNull('functionalities.deleted_at')
                ->select('functionalities.slug as f', 'actions.key as a')
                ->get();

            foreach ($rows as $row) {
                $tenantRolePermissions["{$row->f}:{$row->a}"] = "{$row->f}:{$row->a}";
            }
        }

        $allowedFunctionalities = $this->resolveTenantAllowedFunctionalities($tenantId);
        $allowedFunctionalityLookup = array_fill_keys($allowedFunctionalities, true);

        $merged = [];
        foreach (array_merge($globalPermissions, array_values($tenantRolePermissions)) as $permission) {
            [$functionalitySlug] = explode(':', $permission, 2);
            if (!isset($allowedFunctionalityLookup[$functionalitySlug])) {
                continue;
            }

            $merged[$permission] = $permission;
        }

        ksort($merged);

        return array_values($merged);
    }

    /**
     * Igual a resolveTenantAllowedFunctionalities(), mas retorna os models
     * completos (uuid/name/slug) em vez de só o slug — usado pela tela de
     * perfis da empresa (`TenantRoleFormPage`) pra montar a matriz de
     * permissões sem depender do endpoint global `/functionalities`
     * (`functionalities:read` é permissão de admin da plataforma; um dono
     * de empresa comum, grupo `clients`, nunca tem essa permissão, então
     * chamar o endpoint global de dentro da tela de perfil dava 403).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Functionality\Functionality>
     */
    public function resolveTenantAllowedFunctionalityModels(int $tenantId): \Illuminate\Support\Collection
    {
        $allowedSlugs = $this->resolveTenantAllowedFunctionalities($tenantId);

        return \App\Models\Functionality\Functionality::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('slug', $allowedSlugs)
            ->orderBy('slug')
            ->get();
    }

    /**
     * Lista de slugs de functionality liberados para o tenant — plano
     * (plan_functionalities) como base, com tenant_feature_overrides
     * (roadmap A5, item 19) tendo a PALAVRA FINAL por cima: override
     * is_enabled=true libera mesmo fora do plano (acesso antecipado/
     * piloto), is_enabled=false bloqueia mesmo dentro do plano (desativação
     * pontual). Sem nenhuma linha de override para o tenant, o resultado é
     * idêntico ao comportamento anterior a esta feature (só o plano
     * decide) — usado por tenantPlanAllowsFunctionality() (gate do
     * middleware perm:), resolveTenantPermissionSet() (permissões efetivas
     * do usuário) e resolveTenantAllowedFunctionalityModels() (matriz de
     * permissões da tela de perfis) — único ponto de resolução, sem
     * duplicar a regra override>plano em cada chamador.
     *
     * @return list<string>
     */
    public function resolveTenantAllowedFunctionalities(int $tenantId): array
    {
        $planId = DB::table('tenants')
            ->where('id', $tenantId)
            ->whereNull('deleted_at')
            ->value('plan_id');

        $query = DB::table('functionalities')
            ->whereNull('deleted_at')
            ->where('is_active', true);

        if ($planId) {
            $query->whereExists(function ($subQuery) use ($planId) {
                $subQuery->selectRaw('1')
                    ->from('plan_functionalities')
                    ->whereColumn('plan_functionalities.functionality_id', 'functionalities.id')
                    ->where('plan_functionalities.plan_id', $planId)
                    ->whereNull('plan_functionalities.deleted_at');
            });
        }

        $planAllowed = $query->orderBy('slug')->pluck('slug')->all();

        return $this->applyTenantFeatureOverrides($tenantId, $planAllowed);
    }

    /**
     * @param list<string> $slugs
     * @return list<string>
     */
    private function applyTenantFeatureOverrides(int $tenantId, array $slugs): array
    {
        $overrides = DB::table('tenant_feature_overrides')
            ->join('functionalities', 'functionalities.id', '=', 'tenant_feature_overrides.functionality_id')
            ->where('tenant_feature_overrides.tenant_id', $tenantId)
            ->whereNull('tenant_feature_overrides.deleted_at')
            ->whereNull('functionalities.deleted_at')
            ->where('functionalities.is_active', true)
            ->select('functionalities.slug as slug', 'tenant_feature_overrides.is_enabled as is_enabled')
            ->get();

        if ($overrides->isEmpty()) {
            return $slugs;
        }

        $set = array_fill_keys($slugs, true);

        foreach ($overrides as $override) {
            if ($override->is_enabled) {
                $set[$override->slug] = true;
            } else {
                unset($set[$override->slug]);
            }
        }

        $result = array_keys($set);
        sort($result);

        return $result;
    }
}
