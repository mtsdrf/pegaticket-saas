<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SwitchTenantRequest;
use App\Http\Resources\Tenant\MyTenantResource;
use App\Services\APIResponse;
use App\Services\Tenant\TenantContextService;

class AuthTenantController extends Controller
{
    public function __construct(
        private TenantContextService $service
    ) {
    }

    public function myTenants()
    {
        $tenants = $this->service->listMyTenants();

        return APIResponse::success(
            MyTenantResource::collection($tenants),
            __('messages.auth.my_tenants')
        );
    }

    public function switchTenant(SwitchTenantRequest $request)
    {
        $tokenData = $this->service->switchTenant(
            $request->tenant_uuid
        );

        return APIResponse::success(
            $tokenData,
            __('messages.auth.tenant_switched')
        );
    }
}