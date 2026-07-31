<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\APIResponse;
use App\Services\Permission\PermissionService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthAccessController extends Controller
{
    public function __construct(private PermissionService $permissionService)
    {
    }

    public function show()
    {
        $user = auth()->user();
        $tenantId = null;
        $tenantUuid = null;

        try {
            if ($token = JWTAuth::getToken()) {
                $payload = JWTAuth::setToken($token)->getPayload();
                $tenantId = $payload->get('tenant_id');
                $tenantUuid = $payload->get('tenant_uuid');
            }
        } catch (\Throwable $e) {
        }

        return APIResponse::success(
            $this->permissionService->getAccessProfile((int) $user->id, $tenantId, $tenantUuid),
            __('messages.auth.access_profile_loaded')
        );
    }
}
