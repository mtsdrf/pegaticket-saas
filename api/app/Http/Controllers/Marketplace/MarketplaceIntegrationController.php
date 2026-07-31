<?php

namespace App\Http\Controllers\Marketplace;

use App\DTOs\Marketplace\MarketplaceOrderActionDTO;
use App\DTOs\Marketplace\UpsertMarketplaceIntegrationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\CreateMarketplaceInterruptionRequest;
use App\Http\Requests\Marketplace\ListMarketplaceOrdersRequest;
use App\Http\Requests\Marketplace\MarketplaceOrderActionRequest;
use App\Http\Requests\Marketplace\StoreMarketplaceIntegrationRequest;
use App\Http\Requests\Marketplace\UpdateMarketplaceIntegrationRequest;
use App\Http\Resources\Marketplace\MarketplaceActionResource;
use App\Http\Resources\Marketplace\MarketplaceCatalogSyncResource;
use App\Http\Resources\Marketplace\MarketplaceEventResource;
use App\Http\Resources\Marketplace\MarketplaceIntegrationResource;
use App\Http\Resources\Marketplace\MarketplaceOrderResource;
use App\Models\Marketplace\MarketplaceCatalogSync;
use App\Models\Marketplace\MarketplaceEvent;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceOrder;
use App\Services\APIResponse;
use App\Services\Marketplace\MarketplaceIntegrationService;

class MarketplaceIntegrationController extends Controller
{
    public function __construct(private MarketplaceIntegrationService $service)
    {
    }

    public function index()
    {
        $integrations = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            MarketplaceIntegrationResource::collection($integrations),
            __('messages.marketplace.list')
        );
    }

    public function store(StoreMarketplaceIntegrationRequest $request)
    {
        $dto = UpsertMarketplaceIntegrationDTO::fromArray($request->validated());
        $integration = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            new MarketplaceIntegrationResource($integration),
            __('messages.marketplace.created'),
            201
        );
    }

    public function update(UpdateMarketplaceIntegrationRequest $request, MarketplaceIntegration $marketplaceIntegration)
    {
        $dto = UpsertMarketplaceIntegrationDTO::fromArray($request->validated());
        $integration = $this->service->update($marketplaceIntegration, $dto);

        return APIResponse::success(
            new MarketplaceIntegrationResource($integration),
            __('messages.marketplace.updated')
        );
    }

    public function syncMerchants(MarketplaceIntegration $marketplaceIntegration)
    {
        $integration = $this->service->syncMerchants($marketplaceIntegration);

        return APIResponse::success(
            new MarketplaceIntegrationResource($integration),
            __('messages.marketplace.merchants_synced')
        );
    }

    public function poll(MarketplaceIntegration $marketplaceIntegration)
    {
        $result = $this->service->pollEvents($marketplaceIntegration);

        return APIResponse::success([
            'integration' => (new MarketplaceIntegrationResource($result['integration']))->resolve(),
            'processed' => $result['processed'],
            'acknowledged' => $result['acknowledged'],
        ], __('messages.marketplace.events_polled'));
    }

    public function events(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            MarketplaceEventResource::collection($this->service->listEvents($marketplaceIntegration)),
            __('messages.marketplace.events_listed')
        );
    }

    public function operationsSummary(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->operationsSummary($marketplaceIntegration),
            __('messages.marketplace.operations_summary')
        );
    }

    public function catalogPreview(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->catalogPreview($marketplaceIntegration),
            __('messages.marketplace.catalog_previewed')
        );
    }

    public function syncCatalog(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            new MarketplaceCatalogSyncResource($this->service->syncCatalog($marketplaceIntegration)),
            __('messages.marketplace.catalog_sync_started')
        );
    }

    public function catalogSyncs(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            MarketplaceCatalogSyncResource::collection($this->service->listCatalogSyncs($marketplaceIntegration)),
            __('messages.marketplace.catalog_syncs_listed')
        );
    }

    public function refreshCatalogSync(MarketplaceCatalogSync $marketplaceCatalogSync)
    {
        return APIResponse::success(
            new MarketplaceCatalogSyncResource($this->service->refreshCatalogSync($marketplaceCatalogSync)),
            __('messages.marketplace.catalog_sync_refreshed')
        );
    }

    public function merchantStatus(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->merchantStatus($marketplaceIntegration),
            __('messages.marketplace.merchant_status_listed')
        );
    }

    public function createInterruption(CreateMarketplaceInterruptionRequest $request, MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->createInterruption($marketplaceIntegration, $request->validated()),
            __('messages.marketplace.interruption_created')
        );
    }

    public function deleteInterruption(MarketplaceIntegration $marketplaceIntegration, string $interruptionId)
    {
        return APIResponse::success(
            $this->service->deleteInterruption($marketplaceIntegration, $interruptionId),
            __('messages.marketplace.interruption_deleted')
        );
    }

    public function syncOpeningHours(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->syncOpeningHours($marketplaceIntegration),
            __('messages.marketplace.opening_hours_synced')
        );
    }

    public function orders(ListMarketplaceOrdersRequest $request, MarketplaceIntegration $marketplaceIntegration)
    {
        $validated = $request->validated();
        $list = $this->service->paginateOrders(
            $marketplaceIntegration,
            collect($validated)->only(['search', 'status', 'merchant_uuid', 'queue_status'])->all(),
            (int) ($validated['per_page'] ?? 20),
            (int) ($validated['page'] ?? 1)
        );

        return APIResponse::success(
            MarketplaceOrderResource::collection($list->items()),
            __('messages.marketplace.orders_listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    public function showOrder(MarketplaceOrder $marketplaceOrder)
    {
        return APIResponse::success(
            new MarketplaceOrderResource($this->service->findOrder($marketplaceOrder)),
            __('messages.marketplace.order_show')
        );
    }

    public function health(MarketplaceIntegration $marketplaceIntegration)
    {
        return APIResponse::success(
            $this->service->healthCheck($marketplaceIntegration),
            __('messages.marketplace.health_checked')
        );
    }

    public function performAction(MarketplaceOrderActionRequest $request, MarketplaceOrder $marketplaceOrder)
    {
        $dto = MarketplaceOrderActionDTO::fromArray($request->validated());
        $action = $this->service->performAction($marketplaceOrder->load(['integration', 'merchant']), $dto);

        return APIResponse::success(
            new MarketplaceActionResource($action),
            __('messages.marketplace.action_executed')
        );
    }

    public function cancellationReasons(MarketplaceOrder $marketplaceOrder)
    {
        return APIResponse::success(
            $this->service->cancellationReasons($marketplaceOrder->load(['integration'])),
            __('messages.marketplace.cancellation_reasons_listed')
        );
    }

    public function retryEvent(MarketplaceEvent $marketplaceEvent)
    {
        $event = $this->service->retryEvent($marketplaceEvent->load(['integration', 'merchant']));

        return APIResponse::success(
            new MarketplaceEventResource($event),
            __('messages.marketplace.event_retried')
        );
    }

    public function importOrder(MarketplaceOrder $marketplaceOrder)
    {
        $marketplaceOrder = $this->service->importOrder($marketplaceOrder->load(['integration', 'merchant']));

        return APIResponse::success(
            new MarketplaceOrderResource($marketplaceOrder->load(['merchant', 'actions', 'internalOrder.client'])),
            __('messages.marketplace.order_imported')
        );
    }

    public function refreshOrder(MarketplaceOrder $marketplaceOrder)
    {
        $marketplaceOrder = $this->service->refreshOrder($marketplaceOrder);

        return APIResponse::success(
            new MarketplaceOrderResource($marketplaceOrder),
            __('messages.marketplace.order_refreshed')
        );
    }
}
