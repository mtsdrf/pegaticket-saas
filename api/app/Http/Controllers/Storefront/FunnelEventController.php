<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\CreateFunnelEventDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreFunnelEventRequest;
use App\Http\Resources\Storefront\FunnelEventResource;
use App\Services\APIResponse;
use App\Services\Storefront\FunnelEventService;
use App\Services\Storefront\StorefrontCatalogService;

/**
 * POST /loja/{slug}/eventos/{eventSlug}/funnel-events (roadmap A2) — 100%
 * público, mesmo espírito de CartEventController: resolve tenant+evento
 * publicado via StorefrontCatalogService (404 pra slug/evento inexistente
 * ou não publicado), grava telemetria anônima keyed por session_id.
 */
class FunnelEventController extends Controller
{
    public function __construct(
        private FunnelEventService $service,
        private StorefrontCatalogService $catalogService,
    ) {}

    public function store(StoreFunnelEventRequest $request, string $slug, string $eventSlug)
    {
        $tenant = $this->catalogService->findTenantBySlug($slug);
        $event = $this->catalogService->findPublicEvent($tenant->id, $eventSlug);

        $dto = CreateFunnelEventDTO::fromArray($request->validated(), $tenant->id, $event->id);

        $funnelEvent = $this->service->record($dto);

        return APIResponse::success(
            new FunnelEventResource($funnelEvent),
            __('messages.storefront.funnel_event_recorded'),
            201
        );
    }
}
