<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Tenant\Tenant;
use App\Services\APIResponse;
use App\Services\Tenant\TenantService;
use Illuminate\Http\Request;
use App\DTOs\Tenant\CreateTenantDTO;
use App\DTOs\Tenant\UpdateTenantDTO;

class TenantController extends Controller
{
    public function __construct(
        private TenantService $service
    ) {
    }

    public function index(Request $request)
    {
        $list = $this->service->paginate(
            (int) $request->get('per_page', 15),
            $request->only(['name', 'slug', 'plan_name', 'is_active']),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            TenantResource::collection($list),
            __('messages.tenant.list'),
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

    public function store(StoreTenantRequest $request)
    {
        $dto = CreateTenantDTO::fromArray($request->validated());
        $tenant = $this->service->create($dto);

        return APIResponse::success(
            new TenantResource($tenant),
            __('messages.tenant.created'),
            201
        );
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $dto = UpdateTenantDTO::fromArray($request->validated());
        $tenant = $this->service->update($tenant, $dto);

        return APIResponse::success(
            new TenantResource($tenant),
            __('messages.tenant.updated')
        );
    }

    public function destroy(Tenant $tenant)
    {
        $this->service->delete($tenant);

        return APIResponse::success(
            null,
            __('messages.tenant.deleted'),
            204
        );
    }
}
