<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\UpsertProductPromotionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductPromotionRequest;
use App\Http\Resources\Storefront\ProductPromotionResource;
use App\Services\APIResponse;
use App\Services\Storefront\ProductPromotionService;

class ProductPromotionController extends Controller
{
    public function __construct(
        private ProductPromotionService $service
    ) {
    }

    public function index()
    {
        $promotions = $this->service->list(app('tenant_id'));

        return APIResponse::success(
            ProductPromotionResource::collection($promotions),
            __('messages.product_promotion.list')
        );
    }

    public function store(ProductPromotionRequest $request)
    {
        $dto = UpsertProductPromotionDTO::fromArray($request->validated());

        $promotion = $this->service->upsert(
            app('tenant_id'),
            $dto->productUuid,
            $dto->promoPrice,
            $dto->startsAt,
            $dto->expiresAt,
            $dto->discountType,
            $dto->discountPercentage
        );

        return APIResponse::success(
            new ProductPromotionResource($promotion),
            __('messages.product_promotion.upserted'),
            201
        );
    }

    public function destroy(string $uuid)
    {
        $this->service->delete(app('tenant_id'), $uuid);

        return APIResponse::success(
            null,
            __('messages.product_promotion.deleted'),
            204
        );
    }
}
