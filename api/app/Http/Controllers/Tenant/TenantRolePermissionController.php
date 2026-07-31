<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SyncTenantRolePermissionsRequest;
use App\Models\Tenant\TenantRole;
use App\Services\APIResponse;
use App\Services\Tenant\TenantRolePermissionService;
use App\DTOs\Tenant\SyncTenantRolePermissionsDTO;

class TenantRolePermissionController extends Controller
{
    public function __construct(
        private TenantRolePermissionService $service
    ) {
    }

    public function index(TenantRole $tenantRole)
    {
        $permissions = $this->service->getPermissions($tenantRole);

        return APIResponse::success(
            $permissions,
            __('messages.tenant_role_permission.list')
        );
    }

    public function sync(
        SyncTenantRolePermissionsRequest $request,
        TenantRole $tenantRole
    ) {
        $dto = SyncTenantRolePermissionsDTO::fromArray(
            $request->validated()
        );

        try {
            $this->service->syncPermissions($tenantRole, $dto);
        } catch (\App\Exceptions\ProtectedTenantRoleException $e) {
            return APIResponse::error($e->getMessage(), 403, 'PROTECTED_ROLE');
        }

        return APIResponse::success(
            null,
            __('messages.tenant_role_permission.synced')
        );
    }
}