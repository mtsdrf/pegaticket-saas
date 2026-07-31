<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\StoreFiscalOperationProfileRequest;
use App\Http\Requests\Fiscal\UpdateFiscalOperationProfileRequest;
use App\Http\Resources\Fiscal\FiscalOperationProfileResource;
use App\Models\Fiscal\FiscalOperationProfile;
use App\Services\APIResponse;
use Illuminate\Support\Str;

class FiscalOperationProfileController extends Controller
{
    public function index()
    {
        $profiles = FiscalOperationProfile::query()
            ->where('tenant_id', app('tenant_id'))
            ->whereNull('deleted_at')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return APIResponse::success(FiscalOperationProfileResource::collection($profiles), __('messages.fiscal_operation_profile.list'));
    }

    public function store(StoreFiscalOperationProfileRequest $request)
    {
        $profile = FiscalOperationProfile::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => app('tenant_id'),
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return APIResponse::success(new FiscalOperationProfileResource($profile), __('messages.fiscal_operation_profile.created'), 201);
    }

    public function update(UpdateFiscalOperationProfileRequest $request, FiscalOperationProfile $fiscalOperationProfile)
    {
        abort_unless((int) $fiscalOperationProfile->tenant_id === (int) app('tenant_id'), 404);

        $data = $request->validated();
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $fiscalOperationProfile->fill($data);
        $fiscalOperationProfile->save();

        return APIResponse::success(new FiscalOperationProfileResource($fiscalOperationProfile), __('messages.fiscal_operation_profile.updated'));
    }

    public function destroy(FiscalOperationProfile $fiscalOperationProfile)
    {
        abort_unless((int) $fiscalOperationProfile->tenant_id === (int) app('tenant_id'), 404);

        $fiscalOperationProfile->delete();

        return APIResponse::success(null, __('messages.fiscal_operation_profile.deleted'), 204);
    }
}
