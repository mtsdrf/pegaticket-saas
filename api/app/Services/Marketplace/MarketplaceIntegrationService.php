<?php

namespace App\Services\Marketplace;

use App\DTOs\Marketplace\MarketplaceOrderActionDTO;
use App\DTOs\Marketplace\UpsertMarketplaceIntegrationDTO;
use App\Enums\Marketplace\MarketplaceActionStatus;
use App\Enums\Marketplace\MarketplaceEventStatus;
use App\Enums\Marketplace\MarketplaceIntegrationStatus;
use App\Exceptions\Marketplace\MarketplaceIntegrationException;
use App\Models\Marketplace\MarketplaceAction;
use App\Models\Marketplace\MarketplaceCatalogSync;
use App\Models\Marketplace\MarketplaceEvent;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;
use App\Models\Marketplace\MarketplaceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MarketplaceIntegrationService
{
    public function __construct(
        private MarketplaceProviderRegistry $registry,
        private MarketplaceOrderImportService $marketplaceOrderImportService,
        private MarketplaceCatalogService $marketplaceCatalogService,
        private MarketplaceMerchantAvailabilityService $marketplaceMerchantAvailabilityService,
    ) {
    }

    public function listForTenant(int $tenantId): Collection
    {
        return MarketplaceIntegration::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['merchants'])
            ->withCount(['merchants', 'events', 'orders'])
            ->orderBy('provider')
            ->get();
    }

    public function create(int $tenantId, UpsertMarketplaceIntegrationDTO $dto): MarketplaceIntegration
    {
        return DB::transaction(function () use ($tenantId, $dto) {
            $integration = MarketplaceIntegration::create([
                'tenant_id' => $tenantId,
                'provider' => $dto->provider,
                'name' => $dto->name,
                'environment' => $dto->environment,
                'auth_mode' => 'centralized',
                'status' => MarketplaceIntegrationStatus::Disconnected->value,
                'is_active' => $dto->isActive,
                'client_id' => $dto->clientId,
                'client_secret' => $dto->clientSecret,
                'authorization_code' => $dto->authorizationCode,
                'merchant_id' => $dto->merchantId,
                'webhook_url' => $dto->webhookUrl,
                'polling_merchant_ids' => $dto->pollingMerchantIds,
            ]);

            return $integration->load('merchants');
        });
    }

    public function update(MarketplaceIntegration $integration, UpsertMarketplaceIntegrationDTO $dto): MarketplaceIntegration
    {
        $this->assertBelongsToCurrentTenant($integration);

        $integration->fill([
            'name' => $dto->name,
            'environment' => $dto->environment,
            'is_active' => $dto->isActive,
            'client_id' => $dto->clientId,
            'merchant_id' => $dto->merchantId,
            'webhook_url' => $dto->webhookUrl,
            'polling_merchant_ids' => $dto->pollingMerchantIds,
        ]);

        if ($dto->clientSecret !== null && $dto->clientSecret !== '') {
            $integration->client_secret = $dto->clientSecret;
        }

        if ($dto->authorizationCode !== null && $dto->authorizationCode !== '') {
            $integration->authorization_code = $dto->authorizationCode;
        }

        $integration->save();

        return $integration->fresh()->load('merchants');
    }

    public function syncMerchants(MarketplaceIntegration $integration): MarketplaceIntegration
    {
        $this->assertBelongsToCurrentTenant($integration);
        $provider = $this->registry->for($integration->provider);

        try {
            $merchants = $provider->fetchMerchants($integration);

            DB::transaction(function () use ($integration, $merchants) {
                foreach ($merchants as $merchantData) {
                    MarketplaceMerchant::updateOrCreate(
                        [
                            'integration_id' => $integration->id,
                            'external_id' => $merchantData['external_id'],
                        ],
                        [
                            'tenant_id' => $integration->tenant_id,
                            'name' => $merchantData['name'],
                            'is_active' => (bool) $merchantData['is_active'],
                            'status_payload' => $merchantData['status_payload'] ?? null,
                            'metadata' => $merchantData['metadata'] ?? null,
                            'last_seen_at' => now(),
                        ]
                    );
                }

                $integration->forceFill([
                    'status' => MarketplaceIntegrationStatus::Connected->value,
                    'last_synced_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ])->save();
            });
        } catch (\Throwable $e) {
            $this->flagIntegrationError($integration, $e->getMessage());
            throw $e;
        }

        return $integration->fresh()->load('merchants')->loadCount(['merchants', 'events', 'orders']);
    }

    /**
     * @return array{integration: MarketplaceIntegration, processed: int, acknowledged: int}
     */
    public function pollEvents(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);
        $provider = $this->registry->for($integration->provider);

        try {
            $result = $provider->pollEvents($integration);
            $ingestion = $this->ingestEvents($integration, $result['events'], source: 'polling');
            $processed = $ingestion['processed'];
            $uniqueIds = array_values(array_unique(array_intersect(
                $result['acknowledgable_ids'] ?? [],
                $ingestion['acknowledgeable_ids']
            )));
            $provider->acknowledgeEvents($integration->fresh(), $uniqueIds);

            if ($uniqueIds !== []) {
                MarketplaceEvent::query()
                    ->where('integration_id', $integration->id)
                    ->whereIn('external_event_id', $uniqueIds)
                    ->update(['acknowledged_at' => now()]);
            }

            $integration->forceFill([
                'status' => MarketplaceIntegrationStatus::Connected->value,
                'last_polled_at' => now(),
                'last_error_at' => null,
                'last_error_message' => null,
                'settings' => array_merge($integration->settings ?? [], [
                    'last_poll_processed_at' => now()->toIso8601String(),
                    'last_poll_events_count' => $processed,
                ]),
            ])->save();

            return [
                'integration' => $integration->fresh()->load('merchants')->loadCount(['merchants', 'events', 'orders']),
                'processed' => $processed,
                'acknowledged' => count($uniqueIds),
            ];
        } catch (\Throwable $e) {
            $this->flagIntegrationError($integration, $e->getMessage());
            throw $e;
        }
    }

    public function healthCheck(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->registry->for($integration->provider)->healthCheck($integration);
    }

    public function listEvents(MarketplaceIntegration $integration): Collection
    {
        $this->assertBelongsToCurrentTenant($integration);

        return MarketplaceEvent::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function paginateOrders(
        MarketplaceIntegration $integration,
        array $filters = [],
        int $perPage = 20,
        int $page = 1
    ): LengthAwarePaginator {
        $this->assertBelongsToCurrentTenant($integration);

        $merchantId = null;
        if (filled($filters['merchant_uuid'] ?? null)) {
            $merchantId = MarketplaceMerchant::query()
                ->where('integration_id', $integration->id)
                ->where('uuid', $filters['merchant_uuid'])
                ->whereNull('deleted_at')
                ->value('id');

            if ($merchantId === null) {
                return MarketplaceOrder::query()
                    ->whereRaw('1 = 0')
                    ->paginate($perPage, ['*'], 'page', $page);
            }
        }

        return MarketplaceOrder::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->with(['merchant', 'actions', 'internalOrder.client'])
            ->withCount([
                'events as events_count' => function ($query) use ($integration) {
                    $query->where('integration_id', $integration->id)->whereNull('deleted_at');
                },
            ])
            ->withMax(['events as latest_event_at' => function ($query) use ($integration) {
                $query->where('integration_id', $integration->id)->whereNull('deleted_at');
            }], 'occurred_at')
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $term = mb_strtolower(trim((string) $filters['search']));

                $query->where(function ($where) use ($term) {
                    $like = '%' . $term . '%';
                    $where->whereRaw('LOWER(external_id) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(display_id, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(order_number, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(customer_name, "")) LIKE ?', [$like]);
                });
            })
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', (string) $filters['status']))
            ->when($merchantId !== null, fn ($query) => $query->where('marketplace_merchant_id', $merchantId))
            ->when(($filters['queue_status'] ?? null) === 'imported', fn ($query) => $query->whereNotNull('internal_order_id'))
            ->when(($filters['queue_status'] ?? null) === 'pending_import', fn ($query) => $query->whereNull('internal_order_id')->whereNull('import_error_message'))
            ->when(($filters['queue_status'] ?? null) === 'import_error', fn ($query) => $query->whereNull('internal_order_id')->whereNotNull('import_error_message'))
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function listOrders(MarketplaceIntegration $integration): Collection
    {
        $this->assertBelongsToCurrentTenant($integration);

        return MarketplaceOrder::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->with(['merchant', 'actions', 'internalOrder.client'])
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    public function findOrder(MarketplaceOrder $marketplaceOrder): MarketplaceOrder
    {
        $marketplaceOrder->loadMissing(['integration']);
        $this->assertBelongsToCurrentTenant($marketplaceOrder->integration);

        $events = MarketplaceEvent::query()
            ->where('integration_id', $marketplaceOrder->integration_id)
            ->where('external_order_id', $marketplaceOrder->external_id)
            ->whereNull('deleted_at')
            ->with('merchant')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $order = $marketplaceOrder->fresh(['merchant', 'actions', 'internalOrder.client']);
        $order->setRelation('events', $events);
        $order->setAttribute('events_count', $events->count());
        $order->setAttribute('latest_event_at', $events->first()?->occurred_at);
        $order->setAttribute('queue_status', $order->internal_order_id !== null ? 'imported' : ($order->import_error_message ? 'import_error' : 'pending_import'));

        return $order;
    }

    public function performAction(MarketplaceOrder $order, MarketplaceOrderActionDTO $dto): MarketplaceAction
    {
        $integration = $order->integration;
        $this->assertBelongsToCurrentTenant($integration);

        $merchant = $order->merchant;
        if (!$merchant) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.merchant_not_found'));
        }

        $action = MarketplaceAction::create([
            'tenant_id' => $integration->tenant_id,
            'integration_id' => $integration->id,
            'marketplace_order_id' => $order->id,
            'action' => $dto->action,
            'status' => MarketplaceActionStatus::Pending->value,
            'request_payload' => $dto->payload,
        ]);

        try {
            $response = $this->registry
                ->for($integration->provider)
                ->performOrderAction($integration, $merchant, $order->external_id, $dto->action, $dto->payload);

            $action->forceFill([
                'status' => MarketplaceActionStatus::Succeeded->value,
                'response_payload' => $response,
                'executed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            $action->forceFill([
                'status' => MarketplaceActionStatus::Failed->value,
                'executed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $action->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cancellationReasons(MarketplaceOrder $marketplaceOrder): array
    {
        $marketplaceOrder->loadMissing(['integration']);
        $this->assertBelongsToCurrentTenant($marketplaceOrder->integration);

        return $this->registry
            ->for($marketplaceOrder->integration->provider)
            ->fetchCancellationReasons($marketplaceOrder->integration, $marketplaceOrder->external_id);
    }

    public function dueForPolling(int $limit = 20): Collection
    {
        return MarketplaceIntegration::query()
            ->whereNull('deleted_at')
            ->where('provider', 'ifood')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_polled_at')
                    ->orWhere('last_polled_at', '<=', now()->subSeconds(50));
            })
            ->orderBy('last_polled_at')
            ->limit($limit)
            ->get();
    }

    public function dueForRecovery(int $limit = 20): Collection
    {
        return MarketplaceIntegration::query()
            ->whereNull('deleted_at')
            ->where('provider', 'ifood')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('last_polled_at', '<=', now()->subMinutes(10))
                    ->orWhereNull('last_polled_at')
                    ->orWhere('last_error_at', '>=', now()->subDay())
                    ->orWhereHas('events', function ($events) {
                        $events->whereNull('deleted_at')
                            ->where(function ($inner) {
                                $inner->where('status', MarketplaceEventStatus::Failed->value)
                                    ->orWhereNotNull('dead_lettered_at');
                            });
                    })
                    ->orWhereHas('orders', function ($orders) {
                        $orders->whereNull('deleted_at')
                            ->whereNull('internal_order_id');
                    });
            })
            ->orderBy('last_error_at')
            ->limit($limit)
            ->get();
    }

    public function importOrder(MarketplaceOrder $marketplaceOrder): MarketplaceOrder
    {
        $marketplaceOrder->loadMissing('integration');
        $this->assertBelongsToCurrentTenant($marketplaceOrder->integration);

        try {
            return $this->marketplaceOrderImportService->import($marketplaceOrder);
        } catch (\Throwable $e) {
            $marketplaceOrder->forceFill([
                'import_error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    public function refreshOrder(MarketplaceOrder $marketplaceOrder): MarketplaceOrder
    {
        $marketplaceOrder->loadMissing(['integration', 'merchant']);
        $this->assertBelongsToCurrentTenant($marketplaceOrder->integration);

        $refreshed = $this->syncOrderByExternalId(
            $marketplaceOrder->integration,
            $marketplaceOrder->merchant,
            $marketplaceOrder->external_id
        );

        try {
            if ($refreshed->internal_order_id === null) {
                $this->marketplaceOrderImportService->import($refreshed);
            }
        } catch (\Throwable $e) {
            $refreshed->forceFill([
                'import_error_message' => $e->getMessage(),
            ])->save();
        }

        return $this->findOrder($refreshed);
    }

    /**
     * @return array{integration: MarketplaceIntegration, retried_events: int, refreshed_orders: int}
     */
    public function recoverIntegration(
        MarketplaceIntegration $integration,
        int $eventLimit = 20,
        int $orderLimit = 20
    ): array {
        $retriedEvents = 0;
        $refreshedOrders = 0;

        MarketplaceEvent::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('status', MarketplaceEventStatus::Failed->value)
                    ->orWhereNotNull('dead_lettered_at');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max($eventLimit, 1))
            ->get()
            ->each(function (MarketplaceEvent $event) use ($integration, &$retriedEvents) {
                try {
                    $this->processStoredEvent($event, $integration, $event->merchant);
                    $retriedEvents++;
                } catch (\Throwable $e) {
                    $this->registerEventFailure($event, $e);
                }
            });

        MarketplaceOrder::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->whereNull('internal_order_id')
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->limit(max($orderLimit, 1))
            ->get()
            ->each(function (MarketplaceOrder $order) use (&$refreshedOrders) {
                try {
                    $this->refreshOrder($order);
                    $refreshedOrders++;
                } catch (\Throwable) {
                    // O estado local do pedido já concentra a falha; a
                    // recuperação segue com os demais registros.
                }
            });

        return [
            'integration' => $integration->fresh(),
            'retried_events' => $retriedEvents,
            'refreshed_orders' => $refreshedOrders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogPreview(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceCatalogService->preview($integration);
    }

    public function syncCatalog(MarketplaceIntegration $integration): MarketplaceCatalogSync
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceCatalogService->sync($integration);
    }

    public function listCatalogSyncs(MarketplaceIntegration $integration): Collection
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceCatalogService->listSyncs($integration);
    }

    public function refreshCatalogSync(MarketplaceCatalogSync $sync): MarketplaceCatalogSync
    {
        $sync->loadMissing('integration');
        $this->assertBelongsToCurrentTenant($sync->integration);

        return $this->marketplaceCatalogService->refreshSyncStatus($sync->load(['merchant', 'items.product']));
    }

    /**
     * @return array<string, mixed>
     */
    public function merchantStatus(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceMerchantAvailabilityService->status($integration);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInterruption(MarketplaceIntegration $integration, array $payload): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceMerchantAvailabilityService->createInterruption($integration, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteInterruption(MarketplaceIntegration $integration, string $interruptionId): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceMerchantAvailabilityService->deleteInterruption($integration, $interruptionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncOpeningHours(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);

        return $this->marketplaceMerchantAvailabilityService->syncOpeningHours($integration);
    }

    public function retryEvent(MarketplaceEvent $event): MarketplaceEvent
    {
        $event->loadMissing(['integration', 'merchant']);
        $this->assertBelongsToCurrentTenant($event->integration);

        $this->processStoredEvent($event, $event->integration, $event->merchant);
        $this->attemptPendingOrderImports($event->integration);

        return $event->fresh(['merchant']);
    }

    /**
     * @return array<string, mixed>
     */
    public function operationsSummary(MarketplaceIntegration $integration): array
    {
        $this->assertBelongsToCurrentTenant($integration);
        $now = now();

        $eventsQuery = MarketplaceEvent::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at');

        $ordersQuery = MarketplaceOrder::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at');

        $lastPollAt = optional($integration->last_polled_at)?->toIso8601String();
        $lastWebhookAt = data_get($integration->settings, 'last_webhook_received_at');
        $lastSignalAt = $integration->last_polled_at;
        if (is_string($lastWebhookAt) && filled($lastWebhookAt)) {
            $parsedWebhookAt = Carbon::parse($lastWebhookAt);
            if ($lastSignalAt === null || $parsedWebhookAt->gt($lastSignalAt)) {
                $lastSignalAt = $parsedWebhookAt;
            }
        }
        $silentSinceMinutes = $lastSignalAt?->diffInMinutes(now());
        $stale = $silentSinceMinutes !== null && $silentSinceMinutes > 15;
        $eventsFailed = (clone $eventsQuery)->where('status', MarketplaceEventStatus::Failed->value)->count();
        $eventsDeadLetter = (clone $eventsQuery)->whereNotNull('dead_lettered_at')->count();
        $ordersWithImportError = (clone $ordersQuery)->whereNull('internal_order_id')->whereNotNull('import_error_message')->count();
        $orderSnapshots = (clone $ordersQuery)
            ->get([
                'internal_order_id',
                'import_error_message',
                'last_synced_at',
                'raw_updated_at',
                'imported_at',
            ]);

        $pendingImportAttention = 0;
        $pendingImportCritical = 0;
        $importedWithoutRecentSignal = 0;
        $oldestPendingImportMinutes = null;
        $oldestImportErrorMinutes = null;
        $oldestImportedWithoutSignalMinutes = null;

        foreach ($orderSnapshots as $orderSnapshot) {
            $referenceAt = $orderSnapshot->last_synced_at ?? $orderSnapshot->raw_updated_at;
            $ageInMinutes = $referenceAt ? (int) floor($referenceAt->diffInMinutes($now)) : null;

            if ($orderSnapshot->internal_order_id === null && empty($orderSnapshot->import_error_message)) {
                if ($ageInMinutes !== null) {
                    $oldestPendingImportMinutes = $oldestPendingImportMinutes === null
                        ? $ageInMinutes
                        : max($oldestPendingImportMinutes, $ageInMinutes);
                }

                if ($ageInMinutes !== null && $ageInMinutes >= 15) {
                    $pendingImportCritical++;
                } elseif ($ageInMinutes !== null && $ageInMinutes >= 5) {
                    $pendingImportAttention++;
                }
            }

            if ($orderSnapshot->internal_order_id === null && filled($orderSnapshot->import_error_message) && $ageInMinutes !== null) {
                $oldestImportErrorMinutes = $oldestImportErrorMinutes === null
                    ? $ageInMinutes
                    : max($oldestImportErrorMinutes, $ageInMinutes);
            }

            if ($orderSnapshot->internal_order_id !== null && $ageInMinutes !== null && $ageInMinutes >= 60) {
                $importedWithoutRecentSignal++;
                $oldestImportedWithoutSignalMinutes = $oldestImportedWithoutSignalMinutes === null
                    ? $ageInMinutes
                    : max($oldestImportedWithoutSignalMinutes, $ageInMinutes);
            }
        }

        return [
            'events_total' => (clone $eventsQuery)->count(),
            'events_processed' => (clone $eventsQuery)->where('status', MarketplaceEventStatus::Processed->value)->count(),
            'events_failed' => $eventsFailed,
            'events_dead_letter' => $eventsDeadLetter,
            'events_unacknowledged' => (clone $eventsQuery)->whereNull('acknowledged_at')->count(),
            'orders_total' => (clone $ordersQuery)->count(),
            'orders_imported' => (clone $ordersQuery)->whereNotNull('internal_order_id')->count(),
            'orders_pending_import' => (clone $ordersQuery)->whereNull('internal_order_id')->whereNull('import_error_message')->count(),
            'orders_with_import_error' => $ordersWithImportError,
            'orders_pending_import_attention' => $pendingImportAttention,
            'orders_pending_import_critical' => $pendingImportCritical,
            'orders_imported_without_recent_signal' => $importedWithoutRecentSignal,
            'oldest_pending_import_minutes' => $oldestPendingImportMinutes,
            'oldest_import_error_minutes' => $oldestImportErrorMinutes,
            'oldest_imported_without_signal_minutes' => $oldestImportedWithoutSignalMinutes,
            'last_poll_at' => $lastPollAt,
            'last_webhook_received_at' => $lastWebhookAt,
            'last_error_at' => optional($integration->last_error_at)?->toIso8601String(),
            'last_error_message' => $integration->last_error_message,
            'silent_since_minutes' => $silentSinceMinutes,
            'is_stale' => $stale,
            'needs_attention' => $stale
                || $eventsFailed > 0
                || $eventsDeadLetter > 0
                || $ordersWithImportError > 0
                || $pendingImportAttention > 0
                || $pendingImportCritical > 0
                || $importedWithoutRecentSignal > 0,
        ];
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     * @return array{integration: MarketplaceIntegration, processed: int}
     */
    public function receiveWebhook(MarketplaceIntegration $integration, array $payload): array
    {
        if (!$integration->is_active) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.integration_inactive'));
        }

        $provider = $this->registry->for($integration->provider);
        $events = $provider->normalizeWebhookEvents($integration, $payload);

        $ingestion = $this->ingestEvents($integration, $events, source: 'webhook');
        $processed = $ingestion['processed'];

        $integration->forceFill([
            'status' => MarketplaceIntegrationStatus::Connected->value,
            'last_error_at' => null,
            'last_error_message' => null,
            'settings' => array_merge($integration->settings ?? [], [
                'last_webhook_received_at' => now()->toIso8601String(),
                'last_webhook_events_count' => $processed,
            ]),
        ])->save();

        return [
            'integration' => $integration->fresh()->load('merchants')->loadCount(['merchants', 'events', 'orders']),
            'processed' => $processed,
        ];
    }

    public function generateWebhookUrl(MarketplaceIntegration $integration): ?string
    {
        if (!Route::has('marketplace.webhook.ifood')) {
            return null;
        }

        return route('marketplace.webhook.ifood', ['marketplaceIntegration' => $integration->uuid]);
    }

    private function syncOrderByExternalId(
        MarketplaceIntegration $integration,
        ?MarketplaceMerchant $merchant,
        string $externalOrderId
    ): MarketplaceOrder {
        $orderData = $this->registry->for($integration->provider)->fetchOrder($integration, $externalOrderId);

        return MarketplaceOrder::updateOrCreate(
            [
                'integration_id' => $integration->id,
                'external_id' => $orderData['external_id'],
            ],
            [
                'tenant_id' => $integration->tenant_id,
                'marketplace_merchant_id' => $merchant?->id,
                'display_id' => $orderData['display_id'] ?? null,
                'order_number' => $orderData['order_number'] ?? null,
                'status' => $orderData['status'] ?? null,
                'customer_name' => $orderData['customer_name'] ?? null,
                'total_amount' => $orderData['total_amount'] ?? null,
                'payload' => $orderData['payload'] ?? [],
                'raw_updated_at' => filled($orderData['raw_updated_at'] ?? null) ? Carbon::parse((string) $orderData['raw_updated_at']) : null,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array{processed: int, acknowledgeable_ids: array<int, string>}
     */
    private function ingestEvents(MarketplaceIntegration $integration, array $events, string $source): array
    {
        $processed = 0;
        $acknowledgeableIds = [];

        foreach ($events as $eventData) {
            $merchant = null;
            if (filled($eventData['merchant_external_id'] ?? null)) {
                $merchant = MarketplaceMerchant::query()
                    ->where('integration_id', $integration->id)
                    ->where('external_id', $eventData['merchant_external_id'])
                    ->first();
            }

            $event = MarketplaceEvent::query()
                ->where('integration_id', $integration->id)
                ->where('external_event_id', $eventData['external_event_id'])
                ->first();

            if (!$event) {
                $event = MarketplaceEvent::create([
                    'tenant_id' => $integration->tenant_id,
                    'integration_id' => $integration->id,
                    'marketplace_merchant_id' => $merchant?->id,
                    'external_event_id' => $eventData['external_event_id'],
                    'external_order_id' => $eventData['external_order_id'],
                    'event_type' => $eventData['event_type'],
                    'event_full_code' => $eventData['event_full_code'],
                    'payload' => array_merge(
                        is_array($eventData['payload'] ?? null) ? $eventData['payload'] : [],
                        ['maskats_received_via' => $source]
                    ),
                    'status' => MarketplaceEventStatus::Pending->value,
                    'occurred_at' => $eventData['occurred_at'] ? Carbon::parse((string) $eventData['occurred_at']) : null,
                ]);
            }

            try {
                $this->processStoredEvent($event, $integration, $merchant);
                $processed++;

                if (filled($event->external_event_id)) {
                    $acknowledgeableIds[] = (string) $event->external_event_id;
                }
            } catch (\Throwable $e) {
                $this->registerEventFailure($event, $e);
            }
        }

        $this->attemptPendingOrderImports($integration);

        return [
            'processed' => $processed,
            'acknowledgeable_ids' => array_values(array_unique($acknowledgeableIds)),
        ];
    }

    private function processStoredEvent(
        MarketplaceEvent $event,
        MarketplaceIntegration $integration,
        ?MarketplaceMerchant $merchant
    ): void {
        DB::transaction(function () use ($event, $integration, $merchant) {
            $event->forceFill([
                'status' => MarketplaceEventStatus::Pending->value,
                'last_attempted_at' => now(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
                'error_message' => null,
            ])->save();

            if (filled($event->external_order_id)) {
                $this->syncOrderByExternalId($integration, $merchant, (string) $event->external_order_id);
            }

            $event->forceFill([
                'status' => MarketplaceEventStatus::Processed->value,
                'processed_at' => now(),
                'dead_lettered_at' => null,
                'error_message' => null,
            ])->save();
        });
    }

    private function registerEventFailure(MarketplaceEvent $event, \Throwable $e): void
    {
        MarketplaceEvent::query()
            ->whereKey($event->getKey())
            ->update([
                'status' => MarketplaceEventStatus::Failed->value,
                'processed_at' => null,
                'last_attempted_at' => now(),
                'processing_attempts' => DB::raw('processing_attempts + 1'),
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

        $event->refresh();

        $event->forceFill([
            'dead_lettered_at' => (int) $event->processing_attempts >= 3 ? now() : null,
        ])->save();
    }

    private function attemptPendingOrderImports(MarketplaceIntegration $integration): void
    {
        MarketplaceOrder::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->whereNull('internal_order_id')
            ->whereNotNull('external_id')
            ->orderByDesc('last_synced_at')
            ->get()
            ->each(function (MarketplaceOrder $marketplaceOrder) {
                try {
                    $this->marketplaceOrderImportService->import($marketplaceOrder);
                } catch (\Throwable $e) {
                    $marketplaceOrder->forceFill([
                        'import_error_message' => $e->getMessage(),
                    ])->save();
                }
            });
    }

    private function assertBelongsToCurrentTenant(MarketplaceIntegration $integration): void
    {
        if ((int) $integration->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }

    private function flagIntegrationError(MarketplaceIntegration $integration, string $message): void
    {
        $integration->forceFill([
            'status' => MarketplaceIntegrationStatus::Attention->value,
            'last_error_at' => now(),
            'last_error_message' => $message,
        ])->save();
    }
}
