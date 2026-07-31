<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Tenant\UpdateTenantProfileDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Services\APIResponse;
use App\Services\Tenant\TenantService;

class TenantProfileController extends Controller
{
    public function __construct(
        private TenantService $service
    ) {
    }

    public function show()
    {
        return APIResponse::success(
            new TenantResource(tenant()),
            __('messages.tenant.shown')
        );
    }

    public function update(UpdateTenantProfileRequest $request)
    {
        $dto = UpdateTenantProfileDTO::fromArray($request->validated());
        $tenant = $this->service->updateOwnProfile(tenant(), $dto);

        return APIResponse::success(
            new TenantResource($tenant),
            __('messages.tenant.updated')
        );
    }
}
