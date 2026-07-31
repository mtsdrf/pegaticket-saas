<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantUser;
use App\Services\Auth\JWTService;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Contracts\TenantUserRepositoryInterface;

class TenantContextService
{
    public function __construct(
        private TenantUserRepositoryInterface $repository,
        private JWTService $jwtService
    ) {
    }

    public function listMyTenants()
    {
        return $this->repository->getUserTenants(Auth::id());
    }

    public function switchTenant(string $tenantUuid): array
    {
        $user = Auth::user();

        $tenant = Tenant::where('uuid', $tenantUuid)
            ->where('is_active', true)
            ->firstOrFail();

        $membership = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$membership) {
            throw new \RuntimeException(__('messages.auth.tenant_forbidden'));
        }

        if (!$membership->role || !$membership->role->is_active) {
            throw new \RuntimeException(__('messages.auth.tenant_forbidden'));
        }

        $token = $this->jwtService->issueAccessToken(
            $user,
            $tenant->id,
            $tenant->uuid
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }
}