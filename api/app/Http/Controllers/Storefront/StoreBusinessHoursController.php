<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\UpdateStoreBusinessHoursDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateStoreBusinessHoursRequest;
use App\Http\Resources\Storefront\StoreBusinessHourResource;
use App\Services\APIResponse;
use App\Services\Storefront\StoreBusinessHoursService;

class StoreBusinessHoursController extends Controller
{
    public function __construct(
        private StoreBusinessHoursService $service
    ) {
    }

    public function show()
    {
        $days = $this->service->getForTenant(app('tenant_id'));

        return APIResponse::success(
            StoreBusinessHourResource::collection($days),
            __('messages.store_business_hours.show')
        );
    }

    public function update(UpdateStoreBusinessHoursRequest $request)
    {
        $dto = UpdateStoreBusinessHoursDTO::fromArray($request->validated());

        $days = $this->service->replaceForTenant(app('tenant_id'), $dto->days);

        return APIResponse::success(
            StoreBusinessHourResource::collection($days),
            __('messages.store_business_hours.updated')
        );
    }
}
