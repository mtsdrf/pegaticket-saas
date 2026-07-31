<?php

namespace App\Contracts\Marketplace;

use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;

interface MarketplaceProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function healthCheck(MarketplaceIntegration $integration): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchMerchants(MarketplaceIntegration $integration): array;

    /**
     * @param list<string> $merchantExternalIds
     * @return array{events: array<int, array<string, mixed>>, acknowledgable_ids: list<string>}
     */
    public function pollEvents(MarketplaceIntegration $integration, array $merchantExternalIds = []): array;

    /**
     * @param list<string> $externalEventIds
     */
    public function acknowledgeEvents(MarketplaceIntegration $integration, array $externalEventIds): void;

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public function normalizeWebhookEvents(MarketplaceIntegration $integration, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchOrder(MarketplaceIntegration $integration, string $externalOrderId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCancellationReasons(MarketplaceIntegration $integration, string $externalOrderId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function performOrderAction(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $externalOrderId,
        string $action,
        array $payload = []
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCatalog(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrUpdateCategory(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrUpdateItem(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchCatalogBatch(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $batchId
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchMerchantStatus(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInterruptions(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInterruption(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array;

    public function deleteInterruption(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $interruptionId
    ): void;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public function replaceOpeningHours(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array;
}
