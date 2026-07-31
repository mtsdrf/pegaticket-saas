<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\StorefrontCheckoutDTO;
use App\Exceptions\BelowMinimumOrderException;
use App\Exceptions\CouponUsageLimitReachedException;
use App\Exceptions\DeliveryAreaNotServedException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\StorefrontDisabledException;
use App\Exceptions\StoreClosedException;
use App\Exceptions\StorePickupUnavailableException;
use App\Exceptions\DeliveryUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontCheckoutRequest;
use App\Services\APIResponse;
use App\Services\Storefront\StorefrontCheckoutService;

class StorefrontCheckoutController extends Controller
{
    public function __construct(
        private StorefrontCheckoutService $service
    ) {
    }

    public function store(string $slug, StorefrontCheckoutRequest $request)
    {
        $dto = StorefrontCheckoutDTO::fromArray($request->validated());

        try {
            $order = $this->service->checkout($slug, portal_customer(), $dto);
        } catch (InsufficientStockException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INSUFFICIENT_STOCK');
        } catch (StoreClosedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'STORE_CLOSED');
        } catch (StorefrontDisabledException $e) {
            return APIResponse::error($e->getMessage(), 422, 'STOREFRONT_DISABLED');
        } catch (BelowMinimumOrderException $e) {
            return APIResponse::error($e->getMessage(), 422, 'BELOW_MINIMUM_ORDER');
        } catch (DeliveryAreaNotServedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DELIVERY_AREA_NOT_SERVED');
        } catch (InvalidCouponException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_COUPON');
        } catch (CouponUsageLimitReachedException $e) {
            return APIResponse::error($e->getMessage(), 422, 'COUPON_USAGE_LIMIT_REACHED');
        } catch (StorePickupUnavailableException $e) {
            return APIResponse::error($e->getMessage(), 422, 'STORE_PICKUP_UNAVAILABLE');
        } catch (DeliveryUnavailableException $e) {
            return APIResponse::error($e->getMessage(), 422, 'DELIVERY_UNAVAILABLE');
        }

        return APIResponse::success(
            ['order' => ['uuid' => $order->uuid]],
            __('messages.storefront.checkout_created'),
            201
        );
    }
}
