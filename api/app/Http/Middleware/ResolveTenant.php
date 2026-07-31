<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantUser;
use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return $next($request);
        }

        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (\Throwable $e) {
            return $next($request);
        }

        $tenantUuid = $payload->get('tenant_uuid');
        if (!$tenantUuid) {
            return $next($request);
        }

        $tenant = Tenant::where('uuid', $tenantUuid)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return APIResponse::error(
                __('messages.tenant.invalid'),
                403,
                'TENANT_INVALID'
            );
        }

        $userId = auth()->id();

        $membership = TenantUser::with('role')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$membership || !$membership->role || !$membership->role->is_active) {
            return APIResponse::error(
                __('messages.auth.tenant_forbidden'),
                403,
                'TENANT_FORBIDDEN'
            );
        }

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant_uuid', $tenant->uuid);
        app()->instance('tenant_user', $membership);
        app()->instance('tenant_role', $membership->role);

        return $next($request);
    }
}