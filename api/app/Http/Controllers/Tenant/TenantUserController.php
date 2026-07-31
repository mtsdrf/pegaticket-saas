<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantUserRequest;
use App\Http\Requests\Tenant\UpdateTenantUserRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Models\Tenant\TenantUser;
use App\Services\APIResponse;
use App\Services\Tenant\TenantUserService;
use Illuminate\Http\Request;
use App\DTOs\Tenant\CreateTenantUserDTO;
use App\DTOs\Tenant\UpdateTenantUserDTO;

class TenantUserController extends Controller
{
    public function __construct(
        private TenantUserService $service
    ) {
    }

    public function index(Request $request)
    {
        $list = $this->service->paginate(
            (int) app('tenant_id'),
            (int) $request->get('per_page', 15),
            $request->only(['user_name', 'tenant_name', 'role_name', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            TenantUserResource::collection($list),
            __('messages.tenant_user.list'),
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

    public function store(StoreTenantUserRequest $request)
    {
        $dto = CreateTenantUserDTO::fromArray($request->validated());
        $user = $this->service->create($dto);

        return APIResponse::success(
            new TenantUserResource($user),
            __('messages.tenant_user.created'),
            201
        );
    }

    public function update(UpdateTenantUserRequest $request, TenantUser $tenantUser)
    {
        $dto = UpdateTenantUserDTO::fromArray($request->validated());
        $tenantUser = $this->service->update($tenantUser, $dto);

        return APIResponse::success(
            new TenantUserResource($tenantUser),
            __('messages.tenant_user.updated')
        );
    }

    public function destroy(TenantUser $tenantUser)
    {
        $this->service->delete($tenantUser);

        return APIResponse::success(
            null,
            __('messages.tenant_user.deleted'),
            204
        );
    }
}
