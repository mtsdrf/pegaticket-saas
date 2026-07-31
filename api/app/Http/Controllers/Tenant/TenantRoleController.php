<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRoleRequest;
use App\Http\Requests\Tenant\UpdateTenantRoleRequest;
use App\Http\Resources\Functionality\FunctionalityResource;
use App\Http\Resources\Tenant\TenantRoleResource;
use App\Models\Tenant\TenantRole;
use App\Services\APIResponse;
use App\Services\Permission\PermissionService;
use App\Services\Tenant\TenantRoleService;
use Illuminate\Http\Request;
use App\DTOs\Tenant\CreateTenantRoleDTO;
use App\DTOs\Tenant\UpdateTenantRoleDTO;

class TenantRoleController extends Controller
{
    public function __construct(
        private TenantRoleService $service,
        private PermissionService $permissionService
    ) {
    }

    /**
     * Funcionalidades permitidas pelo plano da tenant ativa, pra montar a
     * matriz de permissões em `/admin/tenant-roles/novo|:uuid/editar` sem
     * exigir a permissão global `functionalities:read` (admin da
     * plataforma) do dono da empresa.
     */
    public function availableFunctionalities()
    {
        $functionalities = $this->permissionService->resolveTenantAllowedFunctionalityModels(
            (int) app('tenant_id')
        );

        return APIResponse::success(
            FunctionalityResource::collection($functionalities),
            __('messages.functionality.list')
        );
    }

    public function index(Request $request)
    {
        $tenantId = app('tenant_id');

        $list = $this->service->paginate(
            $tenantId,
            (int) $request->get('per_page', 15),
            $request->only(['name', 'slug', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            TenantRoleResource::collection($list),
            __('messages.tenant_role.list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function store(StoreTenantRoleRequest $request)
    {
        $dto = CreateTenantRoleDTO::fromArray(
            $request->validated(),
            app('tenant_id')
        );

        try {
            $role = $this->service->create($dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_SLUG');
        }

        return APIResponse::success(
            new TenantRoleResource($role),
            __('messages.tenant_role.created'),
            201
        );
    }

    public function update(UpdateTenantRoleRequest $request, TenantRole $tenantRole)
    {
        $dto = UpdateTenantRoleDTO::fromArray($request->validated());

        try {
            $role = $this->service->update($tenantRole, $dto);
        } catch (\App\Exceptions\DuplicateNameException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DUPLICATE_SLUG');
        } catch (\App\Exceptions\ProtectedTenantRoleException $e) {
            return APIResponse::error($e->getMessage(), 403, 'PROTECTED_ROLE');
        }

        return APIResponse::success(
            new TenantRoleResource($role),
            __('messages.tenant_role.updated')
        );
    }

    public function destroy(TenantRole $tenantRole)
    {
        try {
            $this->service->delete($tenantRole);
        } catch (\App\Exceptions\ProtectedTenantRoleException $e) {
            return APIResponse::error($e->getMessage(), 403, 'PROTECTED_ROLE');
        }

        return APIResponse::success(
            null,
            __('messages.tenant_role.deleted'),
            204
        );
    }
}
