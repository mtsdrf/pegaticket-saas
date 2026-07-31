<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Tenant\SyncTenantFeatureOverridesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SyncTenantFeatureOverridesRequest;
use App\Models\Tenant\Tenant;
use App\Services\APIResponse;
use App\Services\Tenant\TenantFeatureOverrideService;

class TenantFeatureOverrideController extends Controller
{
    public function __construct(
        private TenantFeatureOverrideService $service
    ) {
    }

    public function index(Tenant $tenant)
    {
        $overrides = $this->service->getOverrides($tenant);

        return APIResponse::success(
            $overrides,
            __('messages.tenant_feature_override.list')
        );
    }

    public function sync(SyncTenantFeatureOverridesRequest $request, Tenant $tenant)
    {
        $dto = SyncTenantFeatureOverridesDTO::fromArray($request->validated());
        $this->service->syncOverrides($tenant, $dto);

        return APIResponse::success(
            null,
            __('messages.tenant_feature_override.synced')
        );
    }
}
