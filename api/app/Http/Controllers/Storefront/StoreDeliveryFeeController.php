<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\UpsertStoreDeliveryFeeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreDeliveryFeeRequest;
use App\Http\Resources\Storefront\StoreDeliveryFeeResource;
use App\Services\APIResponse;
use App\Services\Storefront\StoreDeliveryFeeService;

class StoreDeliveryFeeController extends Controller
{
    public function __construct(
        private StoreDeliveryFeeService $service
    ) {
    }

    public function index()
    {
        $fees = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            StoreDeliveryFeeResource::collection($fees),
            __('messages.store_delivery_fee.list')
        );
    }

    public function store(StoreDeliveryFeeRequest $request)
    {
        $dto = UpsertStoreDeliveryFeeDTO::fromArray($request->validated());

        $fee = $this->service->upsert(app('tenant_id'), $dto->bairroUuid, $dto->fee);

        return APIResponse::success(
            new StoreDeliveryFeeResource($fee),
            __('messages.store_delivery_fee.upserted'),
            201
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->delete(app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.store_delivery_fee.deleted'),
            204
        );
    }
}
