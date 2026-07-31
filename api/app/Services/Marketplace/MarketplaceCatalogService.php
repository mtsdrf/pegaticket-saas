<?php

namespace App\Services\Marketplace;

use App\Exceptions\Marketplace\MarketplaceIntegrationException;
use App\Models\Marketplace\MarketplaceCatalogMapping;
use App\Models\Marketplace\MarketplaceCatalogSync;
use App\Models\Marketplace\MarketplaceCatalogSyncItem;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceCatalogService
{
    public function __construct(private MarketplaceProviderRegistry $registry)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(MarketplaceIntegration $integration): array
    {
        $merchant = $this->resolveMerchant($integration);
        $catalog = $this->buildCatalog($integration->tenant_id);

        return [
            'merchant' => [
                'uuid' => $merchant->uuid,
                'external_id' => $merchant->external_id,
                'name' => $merchant->name,
            ],
            'supported_features' => $catalog['supported_features'],
            'pending_features' => $catalog['pending_features'],
            'limitations' => $catalog['limitations'],
            'categories_total' => count($catalog['categories']),
            'items_total' => count($catalog['items']),
            'categories' => $catalog['categories'],
            'items' => $catalog['items'],
        ];
    }

    public function sync(MarketplaceIntegration $integration): MarketplaceCatalogSync
    {
        $merchant = $this->resolveMerchant($integration);
        $catalog = $this->buildCatalog($integration->tenant_id);
        $provider = $this->registry->for($integration->provider);

        /** @var MarketplaceCatalogSync $sync */
        $sync = DB::transaction(function () use ($integration, $merchant, $catalog) {
            $sync = MarketplaceCatalogSync::create([
                'tenant_id' => $integration->tenant_id,
                'integration_id' => $integration->id,
                'marketplace_merchant_id' => $merchant->id,
                'status' => 'running',
                'categories_total' => count($catalog['categories']),
                'items_total' => count($catalog['items']),
                'started_at' => now(),
                'request_snapshot' => [
                    'categories' => $catalog['categories'],
                    'items' => $catalog['items'],
                ],
            ]);

            foreach ($catalog['categories'] as $category) {
                MarketplaceCatalogSyncItem::create([
                    'tenant_id' => $integration->tenant_id,
                    'marketplace_catalog_sync_id' => $sync->id,
                    'entity_type' => 'category',
                    'entity_key' => $category['id'],
                    'external_entity_id' => $category['id'],
                    'status' => 'pending',
                    'request_payload' => $category['request_payload'],
                ]);
            }

            foreach ($catalog['items'] as $item) {
                MarketplaceCatalogSyncItem::create([
                    'tenant_id' => $integration->tenant_id,
                    'marketplace_catalog_sync_id' => $sync->id,
                    'product_id' => $item['product_uuid']
                        ? Product::query()->where('uuid', $item['product_uuid'])->value('id')
                        : null,
                    'entity_type' => 'item',
                    'entity_key' => $item['id'],
                    'external_entity_id' => $item['id'],
                    'status' => 'pending',
                    'request_payload' => $item['request_payload'],
                ]);
            }

            return $sync;
        });

        foreach ($sync->items()->where('entity_type', 'category')->get() as $syncItem) {
            $response = $provider->createOrUpdateCategory($integration, $merchant, $syncItem->request_payload ?? []);
            $this->markSyncItemQueued($syncItem, $response);
        }

        foreach ($sync->items()->where('entity_type', 'item')->get() as $syncItem) {
            $response = $provider->createOrUpdateItem($integration, $merchant, $syncItem->request_payload ?? []);
            $this->markSyncItemQueued($syncItem, $response);
        }

        return $this->refreshSyncStatus($sync->fresh(['merchant', 'items.product']), eagerLoadBatch: false);
    }

    public function refreshSyncStatus(MarketplaceCatalogSync $sync, bool $eagerLoadBatch = true): MarketplaceCatalogSync
    {
        $sync->loadMissing(['integration', 'merchant', 'items']);

        $provider = $this->registry->for($sync->integration->provider);

        foreach ($sync->items as $item) {
            if (!$item->batch_id || in_array($item->status, ['completed', 'failed'], true)) {
                continue;
            }

            $response = $provider->fetchCatalogBatch($sync->integration, $sync->merchant, $item->batch_id);
            $batchStatus = strtoupper((string) ($response['batchStatus'] ?? $response['status'] ?? 'PENDING'));
            $results = collect($response['results'] ?? []);
            $firstFailed = $results->first(fn (mixed $row) => strtoupper((string) data_get($row, 'result', '')) !== 'SUCCESS');

            if ($batchStatus === 'COMPLETED' && !$firstFailed) {
                $item->forceFill([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'response_payload' => $response,
                    'error_message' => null,
                ])->save();
            } elseif ($batchStatus === 'COMPLETED') {
                $item->forceFill([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'response_payload' => $response,
                    'error_message' => (string) (data_get($firstFailed, 'message') ?? __('messages.marketplace.catalog_sync_item_failed')),
                ])->save();
            } elseif ($eagerLoadBatch) {
                $item->forceFill([
                    'status' => 'queued',
                    'response_payload' => $response,
                ])->save();
            }
        }

        $sync->refresh();
        $items = $sync->items()->get();
        $processed = $items->whereIn('status', ['completed', 'failed'])->count();
        $success = $items->where('status', 'completed')->count();
        $failed = $items->where('status', 'failed')->count();
        $pending = $items->whereIn('status', ['pending', 'queued'])->count();

        $sync->forceFill([
            'processed_count' => $processed,
            'success_count' => $success,
            'failed_count' => $failed,
            'status' => $failed > 0 ? 'partial_failure' : ($pending > 0 ? 'queued' : 'completed'),
            'finished_at' => $pending > 0 ? null : now(),
            'response_snapshot' => [
                'pending' => $pending,
                'success' => $success,
                'failed' => $failed,
            ],
        ])->save();

        $this->upsertMappings($sync, $items);

        return $sync->fresh(['merchant', 'items.product']);
    }

    public function listSyncs(MarketplaceIntegration $integration): Collection
    {
        return MarketplaceCatalogSync::query()
            ->where('integration_id', $integration->id)
            ->whereNull('deleted_at')
            ->with(['merchant', 'items.product'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return array{
     *   supported_features: array<int, string>,
     *   pending_features: array<int, string>,
     *   limitations: array<int, string>,
     *   categories: array<int, array<string, mixed>>,
     *   items: array<int, array<string, mixed>>
     * }
     */
    private function buildCatalog(int $tenantId): array
    {
        $products = Product::query()
            ->with(['productType.productCategory', 'optionGroups.options'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.catalog_has_no_available_products'));
        }

        $categories = [];
        $items = [];

        foreach ($products as $product) {
            $category = $product->productType?->productCategory;
            $categoryUuid = $category?->uuid ?? 'sem-categoria';
            $categoryName = $category?->name ?? 'Geral';
            $categoryExternalId = 'mk-cat-' . $categoryUuid;

            if (!isset($categories[$categoryExternalId])) {
                $categories[$categoryExternalId] = [
                    'id' => $categoryExternalId,
                    'name' => $categoryName,
                    'request_payload' => [
                        'id' => $categoryExternalId,
                        'name' => $categoryName,
                        'status' => 'AVAILABLE',
                        'template' => 'DEFAULT',
                    ],
                ];
            }

            $productExternalId = 'mk-prod-' . $product->uuid;
            $itemExternalId = 'mk-item-' . $product->uuid;
            $optionGroups = [];
            $options = [];

            foreach ($product->optionGroups->where('is_active', true) as $group) {
                $groupExternalId = 'mk-opt-group-' . $group->uuid;
                $groupOptionExternalIds = [];

                foreach ($group->options->where('is_available', true) as $option) {
                    $optionExternalId = 'mk-opt-' . $option->uuid;
                    $groupOptionExternalIds[] = $optionExternalId;

                    $options[] = [
                        'id' => $optionExternalId,
                        'name' => Str::limit($option->name, 120, ''),
                        'description' => $option->description ? Str::limit($option->description, 1000, '') : null,
                        'status' => 'AVAILABLE',
                        'price' => ['value' => (float) $option->price],
                    ];
                }

                if ($groupOptionExternalIds === []) {
                    continue;
                }

                $optionGroups[] = [
                    'id' => $groupExternalId,
                    'name' => Str::limit($group->name, 120, ''),
                    'description' => $group->description ? Str::limit($group->description, 1000, '') : null,
                    'minimum' => (int) $group->min_select,
                    'maximum' => (int) $group->max_select,
                    'optionIds' => $groupOptionExternalIds,
                ];
            }

            $items[] = [
                'id' => $itemExternalId,
                'product_uuid' => $product->uuid,
                'product_name' => $product->name,
                'category_name' => $categoryName,
                'option_groups_count' => count($optionGroups),
                'options_count' => count($options),
                'request_payload' => [
                    'item' => [
                        'id' => $itemExternalId,
                        'type' => 'DEFAULT',
                        'categoryId' => $categoryExternalId,
                        'status' => $product->is_available ? 'AVAILABLE' : 'UNAVAILABLE',
                        'price' => ['value' => (float) $product->price],
                    ],
                    'products' => [[
                        'id' => $productExternalId,
                        'name' => Str::limit($product->name, 120, ''),
                        'description' => $product->description ? Str::limit($product->description, 1000, '') : null,
                        'status' => $product->is_available ? 'AVAILABLE' : 'UNAVAILABLE',
                        'externalCode' => $product->sku ?: $product->uuid,
                    ]],
                    'optionGroups' => $optionGroups,
                    'options' => $options,
                ],
            ];
        }

        return [
            'supported_features' => ['categories', 'simple_items', 'complement_groups', 'complements'],
            'pending_features' => ['combos', 'pizza'],
            'limitations' => [
                __('messages.marketplace.catalog_limitation_no_combos'),
            ],
            'categories' => array_values($categories),
            'items' => $items,
        ];
    }

    /**
     * @param Collection<int, MarketplaceCatalogSyncItem> $items
     */
    private function upsertMappings(MarketplaceCatalogSync $sync, Collection $items): void
    {
        foreach ($items->where('status', 'completed') as $item) {
            MarketplaceCatalogMapping::updateOrCreate(
                [
                    'integration_id' => $sync->integration_id,
                    'marketplace_merchant_id' => $sync->marketplace_merchant_id,
                    'entity_type' => $item->entity_type,
                    'entity_key' => $item->entity_key,
                ],
                [
                    'tenant_id' => $sync->tenant_id,
                    'internal_uuid' => $item->product?->uuid,
                    'external_entity_id' => $item->external_entity_id ?? $item->entity_key,
                    'metadata' => [
                        'batch_id' => $item->batch_id,
                        'last_status' => $item->status,
                    ],
                    'last_synced_at' => now(),
                ]
            );
        }
    }

    private function resolveMerchant(MarketplaceIntegration $integration): MarketplaceMerchant
    {
        $merchant = null;

        if ($integration->merchant_id) {
            $merchant = MarketplaceMerchant::query()
                ->where('integration_id', $integration->id)
                ->where('external_id', $integration->merchant_id)
                ->whereNull('deleted_at')
                ->first();
        }

        $merchant ??= MarketplaceMerchant::query()
            ->where('integration_id', $integration->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        if (!$merchant) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.merchant_not_found'));
        }

        return $merchant;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function markSyncItemQueued(MarketplaceCatalogSyncItem $item, array $response): void
    {
        $item->forceFill([
            'status' => 'queued',
            'batch_id' => (string) ($response['batchId'] ?? $response['batch_id'] ?? ''),
            'response_payload' => $response,
            'error_message' => null,
        ])->save();
    }
}
