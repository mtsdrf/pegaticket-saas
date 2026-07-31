<?php

namespace App\Http\Controllers\TenantSettings;

use App\DTOs\TenantSettings\UpdateTenantSettingsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantSettings\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantSettings\TenantSettingsResource;
use App\Services\APIResponse;
use App\Services\Tenant\TenantSettingsService;

class TenantSettingsController extends Controller
{
    public function __construct(
        private TenantSettingsService $service
    ) {
    }

    public function show()
    {
        $settings = $this->service->getForTenant(app('tenant_id'));

        return APIResponse::success(
            new TenantSettingsResource($settings),
            __('messages.tenant_settings.show')
        );
    }

    public function update(UpdateTenantSettingsRequest $request)
    {
        $dto = UpdateTenantSettingsDTO::fromArray($request->validated());

        $settings = $this->service->update(app('tenant_id'), $dto);

        return APIResponse::success(
            new TenantSettingsResource($settings),
            __('messages.tenant_settings.updated')
        );
    }
}
