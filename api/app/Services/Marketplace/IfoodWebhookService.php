<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;

class IfoodWebhookService
{
    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    public function isKeepAlivePayload(array $payload): bool
    {
        if (array_is_list($payload)) {
            return false;
        }

        $code = strtoupper((string) ($payload['code'] ?? $payload['fullCode'] ?? ''));

        return $code === 'KEEPALIVE';
    }

    public function hasValidSignature(MarketplaceIntegration $integration, string $rawBody, ?string $signature): bool
    {
        $clientSecret = (string) ($integration->client_secret ?? '');
        $provided = trim((string) $signature);

        if ($clientSecret === '' || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $clientSecret);

        return hash_equals(strtolower($expected), strtolower($provided));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function presenceResponse(MarketplaceIntegration $integration, array $payload): array
    {
        $merchantIds = collect($payload['merchantIds'] ?? [])
            ->filter(fn (mixed $id) => is_string($id) && $id !== '')
            ->values();

        if ($merchantIds->isEmpty()) {
            return [];
        }

        $onlineMerchantIds = MarketplaceMerchant::query()
            ->where('integration_id', $integration->id)
            ->where('tenant_id', $integration->tenant_id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('external_id', $merchantIds->all())
            ->pluck('external_id')
            ->values()
            ->all();

        return [
            'merchantIds' => $onlineMerchantIds,
        ];
    }
}
