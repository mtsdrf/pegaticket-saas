<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\CreateCouponDTO;
use App\DTOs\Storefront\UpdateCouponDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CouponRequest;
use App\Http\Resources\Storefront\CouponResource;
use App\Services\APIResponse;
use App\Services\Storefront\CouponService;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $service
    ) {
    }

    public function index()
    {
        $coupons = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            CouponResource::collection($coupons),
            __('messages.coupon.list')
        );
    }

    public function store(CouponRequest $request)
    {
        $dto = CreateCouponDTO::fromArray($request->validated());

        $coupon = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            new CouponResource($coupon),
            __('messages.coupon.created'),
            201
        );
    }

    public function update(string $uuid, CouponRequest $request)
    {
        $dto = UpdateCouponDTO::fromArray($request->validated());

        $coupon = $this->service->update(app('tenant_id'), $uuid, $dto);

        return APIResponse::success(
            new CouponResource($coupon),
            __('messages.coupon.updated')
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->delete(app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.coupon.deleted'),
            204
        );
    }
}
