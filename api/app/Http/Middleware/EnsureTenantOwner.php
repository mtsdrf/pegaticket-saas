<?php

namespace App\Http\Middleware;

use App\Services\APIResponse;
use App\Services\Permission\PermissionService;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantOwner
{
    public function __construct(
        private PermissionService $permissionService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $tenantId = tenant_id();

        if (!$user || $tenantId === null) {
            return APIResponse::error(__('messages.auth.forbidden'), 403, 'FORBIDDEN');
        }

        if (!$this->permissionService->userIsTenantOwner((int) $user->id, (int) $tenantId)) {
            return APIResponse::error(
                __('messages.subscription.owner_only'),
                403,
                'TENANT_OWNER_REQUIRED'
            );
        }

        return $next($request);
    }
}
