<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\CreateCartEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreCartEventRequest;
use App\Http\Resources\Storefront\CartEventResource;
use App\Services\APIResponse;
use App\Services\Storefront\CartEventService;
use App\Services\Storefront\StorefrontCatalogService;

/**
 * POST /loja/{slug}/eventos-carrinho (roadmap A3.14) — 100% público, mesmo
 * espírito das demais rotas /loja/{slug}/*: resolve o tenant pelo slug via
 * StorefrontCatalogService::findTenantBySlug() (404 tanto pra slug
 * inexistente quanto pra tenant sem a functionality 'storefront' no plano).
 */
class CartEventController extends Controller
{
    public function __construct(
        private CartEventService $service,
        private StorefrontCatalogService $catalogService,
    ) {
    }

    public function store(StoreCartEventRequest $request, string $slug)
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);

        $dto = CreateCartEventDTO::fromArray($request->validated(), $tenant->id);

        $event = $this->service->record($dto);

        return APIResponse::success(
            new CartEventResource($event),
            __('messages.storefront.cart_event_recorded'),
            201
        );
    }
}
