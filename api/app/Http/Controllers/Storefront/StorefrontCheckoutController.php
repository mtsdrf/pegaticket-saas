<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\StorefrontCheckoutDTO;
use App\Exceptions\BelowMinimumSaleException;
use App\Exceptions\CouponUsageLimitReachedException;
use App\Exceptions\InsufficientChannelQuotaException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\StorefrontDisabledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontCheckoutRequest;
use App\Services\APIResponse;
use App\Services\Storefront\StorefrontCheckoutService;

class StorefrontCheckoutController extends Controller
{
    public function __construct(
        private StorefrontCheckoutService $service
    ) {}

    public function store(string $slug, StorefrontCheckoutRequest $request)
    {
        $dto = StorefrontCheckoutDTO::fromArray($request->validated());

        try {
            $sale = $this->service->checkout($slug, portal_customer(), $dto, $request->ip());
        } catch (StorefrontDisabledException $e) {
            return APIResponse::error($e->getMessage(), 422, 'STOREFRONT_DISABLED');
        } catch (BelowMinimumSaleException $e) {
            return APIResponse::error($e->getMessage(), 422, 'BELOW_MINIMUM_ORDER');
        } catch (InvalidCouponException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_COUPON');
        } catch (CouponUsageLimitReachedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COUPON_USAGE_LIMIT_REACHED');
        } catch (InsufficientChannelQuotaException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_CHANNEL_QUOTA');
        }

        return APIResponse::success(
            ['sale' => ['uuid' => $sale->uuid]],
            __('messages.storefront.checkout_created'),
            201
        );
    }
}
