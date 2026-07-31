<?php

namespace App\Http\Middleware;

use App\Services\Permission\PermissionService;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function __construct(private PermissionService $permissionService)
    {
    }

    public function handle(Request $request, Closure $next, string $functionalitySlug, string $actionKey)
    {
        $user = auth()->user();

        if (!$user) {
            return \App\Services\APIResponse::error(__('messages.auth.forbidden'), 403, 'FORBIDDEN');
        }

        $allowedViaGroup = $this->permissionService->userCanViaGroups($user->id, $functionalitySlug, $actionKey);

        $tenantRole = app()->bound('tenant_role') ? app('tenant_role') : null;
        $allowedViaTenantRole = $tenantRole
            ? $this->permissionService->userCanViaTenantRole($tenantRole->id, $functionalitySlug, $actionKey)
            : false;

        if (!$allowedViaGroup && !$allowedViaTenantRole) {
            return \App\Services\APIResponse::error(__('messages.auth.forbidden'), 403, 'FORBIDDEN');
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if ($tenant && !$this->permissionService->tenantPlanAllowsFunctionality($tenant->id, $functionalitySlug)) {
            return \App\Services\APIResponse::error(
                __('messages.plan.upgrade_required'),
                403,
                'PLAN_UPGRADE_REQUIRED'
            );
        }

        // Assinatura suspensa/cancelada bloqueia acesso operacional (roadmap
        // 1B), MAS a própria tela de assinatura fica sempre acessível para o
        // dono conseguir reativar/regularizar.
        if (
            $tenant
            && $functionalitySlug !== 'subscription'
            && $this->permissionService->tenantSubscriptionBlocksAccess($tenant->id)
        ) {
            return \App\Services\APIResponse::error(
                __('messages.subscription.suspended_access'),
                403,
                'SUBSCRIPTION_SUSPENDED'
            );
        }

        return $next($request);
    }
}
