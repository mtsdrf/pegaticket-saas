<?php

namespace App\Services\Marketplace;

use App\Contracts\Marketplace\MarketplaceProviderInterface;
use App\Exceptions\Marketplace\MarketplaceIntegrationException;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class IfoodMarketplaceProvider implements MarketplaceProviderInterface
{
    private const TOKEN_EXPIRY_SAFETY_SECONDS = 120;

    public function healthCheck(MarketplaceIntegration $integration): array
    {
        $merchants = $this->fetchMerchants($integration);

        return [
            'ok' => true,
            'provider' => 'ifood',
            'merchant_count' => count($merchants),
        ];
    }

    public function fetchMerchants(MarketplaceIntegration $integration): array
    {
        $response = $this->request($integration)
            ->get($this->resolvePath('merchant_list'));

        return collect($this->unwrapList($response->json()))
            ->map(fn (mixed $item) => $this->normalizeMerchant(is_array($item) ? $item : []))
            ->all();
    }

    public function pollEvents(MarketplaceIntegration $integration, array $merchantExternalIds = []): array
    {
        $request = $this->request($integration);

        $merchantIds = $merchantExternalIds !== [] ? $merchantExternalIds : $integration->pollingMerchantIdsList();
        if ($merchantIds !== []) {
            $request->withHeaders([
                'x-polling-merchants' => implode(',', $merchantIds),
            ]);
        }

        $response = $request->get($this->resolvePath('events_polling'));
        $rawEvents = $this->unwrapList($response->json());

        $events = collect($rawEvents)
            ->map(fn (mixed $item) => $this->normalizeEvent(is_array($item) ? $item : []))
            ->values()
            ->all();

        return [
            'events' => $events,
            'acknowledgable_ids' => collect($events)
                ->pluck('external_event_id')
                ->filter(fn (?string $id) => filled($id))
                ->values()
                ->all(),
        ];
    }

    public function acknowledgeEvents(MarketplaceIntegration $integration, array $externalEventIds): void
    {
        if ($externalEventIds === []) {
            return;
        }

        $this->request($integration)
            ->post($this->resolvePath('events_acknowledgment'), [
                'acknowledgedEventIds' => array_values($externalEventIds),
            ])
            ->throw();
    }

    public function normalizeWebhookEvents(MarketplaceIntegration $integration, array $payload): array
    {
        return collect($this->unwrapList($payload))
            ->map(fn (mixed $item) => $this->normalizeEvent(is_array($item) ? $item : []))
            ->values()
            ->all();
    }

    public function fetchOrder(MarketplaceIntegration $integration, string $externalOrderId): array
    {
        $response = $this->request($integration)
            ->get(str_replace('{id}', $externalOrderId, $this->resolvePath('order_details')));

        return $this->normalizeOrder(is_array($response->json()) ? $response->json() : []);
    }

    public function fetchCancellationReasons(MarketplaceIntegration $integration, string $externalOrderId): array
    {
        $response = $this->request($integration)
            ->get(str_replace('{id}', $externalOrderId, $this->resolvePath('order_cancellation_reasons')));

        return collect($this->unwrapList(is_array($response->json()) ? $response->json() : []))
            ->map(fn (mixed $item) => is_array($item) ? [
                'code' => (string) ($item['code'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'metadata' => $item,
            ] : [])
            ->filter(fn (array $item) => $item !== [] && $item['code'] !== '')
            ->values()
            ->all();
    }

    public function performOrderAction(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $externalOrderId,
        string $action,
        array $payload = []
    ): array {
        $path = $this->resolveActionPath($action, $externalOrderId);

        $body = array_filter([
            'merchantId' => $merchant->external_id,
            'reason' => $payload['reason'] ?? null,
            'code' => $payload['code'] ?? null,
        ], fn (mixed $value) => $value !== null && $value !== '');

        if ($action === 'cancel' && isset($payload['reason']) && is_string($payload['reason'])) {
            $body['reason'] = $payload['reason'];
        }

        $response = $this->request($integration)
            ->post($path, $body);

        return is_array($response->json()) ? $response->json() : ['status' => 'accepted'];
    }

    public function fetchCatalog(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array
    {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('catalog_categories'));

        $response = $this->request($integration)
            ->get($path, ['include_items' => 'true']);

        return collect($this->unwrapList(is_array($response->json()) ? $response->json() : []))
            ->map(fn (mixed $item) => is_array($item) ? $item : [])
            ->all();
    }

    public function createOrUpdateCategory(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('catalog_categories'));

        $response = $this->request($integration)
            ->post($path, $payload);

        return is_array($response->json()) ? $response->json() : ['status' => 'accepted'];
    }

    public function createOrUpdateItem(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('catalog_items'));

        $response = $this->request($integration)
            ->put($path, $payload);

        return is_array($response->json()) ? $response->json() : ['status' => 'accepted'];
    }

    public function fetchCatalogBatch(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $batchId
    ): array {
        $path = str_replace(
            ['{merchantId}', '{batchId}'],
            [$merchant->external_id, $batchId],
            $this->resolvePath('catalog_batch')
        );

        $response = $this->request($integration)->get($path);

        return is_array($response->json()) ? $response->json() : [];
    }

    public function fetchMerchantStatus(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array
    {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('merchant_status'));

        $response = $this->request($integration)->get($path);

        return collect($this->unwrapList(is_array($response->json()) ? $response->json() : []))
            ->map(fn (mixed $item) => is_array($item) ? $item : [])
            ->values()
            ->all();
    }

    public function listInterruptions(MarketplaceIntegration $integration, MarketplaceMerchant $merchant): array
    {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('merchant_interruptions'));

        $response = $this->request($integration)->get($path);

        return collect($this->unwrapList(is_array($response->json()) ? $response->json() : []))
            ->map(fn (mixed $item) => is_array($item) ? $item : [])
            ->values()
            ->all();
    }

    public function createInterruption(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('merchant_interruptions'));

        $response = $this->request($integration)->post($path, $payload);

        return is_array($response->json()) ? $response->json() : ['status' => 'accepted'];
    }

    public function deleteInterruption(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        string $interruptionId
    ): void {
        $path = str_replace(
            ['{merchantId}', '{interruptionId}'],
            [$merchant->external_id, $interruptionId],
            $this->resolvePath('merchant_interruption_delete')
        );

        $this->request($integration)->delete($path)->throw();
    }

    public function replaceOpeningHours(
        MarketplaceIntegration $integration,
        MarketplaceMerchant $merchant,
        array $payload
    ): array {
        $path = str_replace('{merchantId}', $merchant->external_id, $this->resolvePath('merchant_opening_hours'));

        $response = $this->request($integration)->put($path, $payload);

        return collect($this->unwrapList(is_array($response->json()) ? $response->json() : []))
            ->map(fn (mixed $item) => is_array($item) ? $item : [])
            ->values()
            ->all();
    }

    private function request(MarketplaceIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.ifood.timeout_seconds', 15))
            ->connectTimeout((int) config('services.ifood.connect_timeout_seconds', 5))
            ->retry(2, 300)
            ->baseUrl((string) config('services.ifood.base_url'))
            ->withToken($this->resolveAccessToken($integration))
            ->throw(function ($response) {
                throw new MarketplaceIntegrationException(
                    $this->extractProviderError($response->json()) ?? __('messages.marketplace.provider_unavailable')
                );
            });
    }

    private function resolveAccessToken(MarketplaceIntegration $integration): string
    {
        $validUntil = $integration->access_token_expires_at;
        if (
            filled($integration->access_token) &&
            $validUntil !== null &&
            $validUntil->gt(now()->addSeconds(self::TOKEN_EXPIRY_SAFETY_SECONDS))
        ) {
            return (string) $integration->access_token;
        }

        if (blank($integration->client_id) || blank($integration->client_secret)) {
            throw new MarketplaceIntegrationException(__('messages.marketplace.credentials_required'));
        }

        $grantPayload = [
            'clientId' => $integration->client_id,
            'clientSecret' => $integration->client_secret,
        ];

        if (filled($integration->refresh_token)) {
            $grantPayload['grantType'] = 'refresh_token';
            $grantPayload['refreshToken'] = $integration->refresh_token;
        } elseif (filled($integration->authorization_code)) {
            $grantPayload['grantType'] = 'authorization_code';
            $grantPayload['authorizationCode'] = $integration->authorization_code;
        } else {
            throw new MarketplaceIntegrationException(__('messages.marketplace.credentials_required'));
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.ifood.timeout_seconds', 15))
            ->connectTimeout((int) config('services.ifood.connect_timeout_seconds', 5))
            ->retry(2, 300)
            ->post((string) config('services.ifood.oauth_token_url'), $grantPayload)
            ->throw(function ($response) {
                throw new MarketplaceIntegrationException(
                    $this->extractProviderError($response->json()) ?? __('messages.marketplace.authentication_failed')
                );
            });

        $payload = is_array($response->json()) ? $response->json() : [];
        $accessToken = (string) ($payload['accessToken'] ?? $payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new MarketplaceIntegrationException(__('messages.marketplace.authentication_failed'));
        }

        $expiresIn = (int) ($payload['expiresIn'] ?? $payload['expires_in'] ?? 21600);
        $refreshToken = (string) ($payload['refreshToken'] ?? $payload['refresh_token'] ?? $integration->refresh_token ?? '');
        $refreshExpiresIn = (int) ($payload['refreshTokenExpiresIn'] ?? $payload['refresh_token_expires_in'] ?? 0);

        $integration->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken !== '' ? $refreshToken : $integration->refresh_token,
            'access_token_expires_at' => now()->addSeconds(max($expiresIn, 60)),
            'refresh_token_expires_at' => $refreshExpiresIn > 0 ? now()->addSeconds($refreshExpiresIn) : $integration->refresh_token_expires_at,
            'last_connected_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ])->save();

        return $accessToken;
    }

    private function resolvePath(string $key): string
    {
        return (string) Arr::get(config('services.ifood.paths'), $key);
    }

    private function resolveActionPath(string $action, string $externalOrderId): string
    {
        $path = (string) Arr::get(config('services.ifood.paths.actions'), $action);

        if ($path === '') {
            throw new MarketplaceIntegrationException(__('messages.marketplace.action_not_supported'));
        }

        return str_replace('{id}', $externalOrderId, $path);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     * @return array<int, mixed>
     */
    private function unwrapList(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['items', 'data', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $merchant
     * @return array<string, mixed>
     */
    private function normalizeMerchant(array $merchant): array
    {
        return [
            'external_id' => (string) ($merchant['id'] ?? $merchant['merchantId'] ?? ''),
            'name' => (string) ($merchant['name'] ?? $merchant['corporateName'] ?? $merchant['displayName'] ?? 'Loja sem nome'),
            'is_active' => (bool) ($merchant['active'] ?? $merchant['enabled'] ?? true),
            'status_payload' => isset($merchant['status']) && is_array($merchant['status']) ? $merchant['status'] : $merchant,
            'metadata' => $merchant,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $event): array
    {
        $metadata = isset($event['metadata']) && is_array($event['metadata']) ? $event['metadata'] : [];

        return [
            'external_event_id' => (string) ($event['id'] ?? $event['eventId'] ?? ''),
            'external_order_id' => (string) ($metadata['orderId'] ?? $event['orderId'] ?? $event['fullCode'] ?? ''),
            'merchant_external_id' => (string) ($event['merchantId'] ?? $metadata['merchantId'] ?? ''),
            'event_type' => (string) ($event['code'] ?? $event['type'] ?? 'unknown'),
            'event_full_code' => (string) ($event['fullCode'] ?? $event['code'] ?? ''),
            'occurred_at' => $event['createdAt'] ?? $event['created_at'] ?? null,
            'payload' => $event,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function normalizeOrder(array $order): array
    {
        $customer = isset($order['customer']) && is_array($order['customer']) ? $order['customer'] : [];
        $total = Arr::get($order, 'total.orderAmount')
            ?? Arr::get($order, 'totals.total')
            ?? Arr::get($order, 'orderAmount')
            ?? null;

        return [
            'external_id' => (string) ($order['id'] ?? ''),
            'display_id' => (string) ($order['displayId'] ?? ''),
            'order_number' => (string) ($order['orderNumber'] ?? $order['displayId'] ?? ''),
            'status' => (string) ($order['orderState'] ?? Arr::get($order, 'status.code') ?? Arr::get($order, 'status') ?? ''),
            'customer_name' => (string) ($customer['name'] ?? $customer['fullName'] ?? ''),
            'total_amount' => is_numeric($total) ? (float) $total : null,
            'merchant_external_id' => (string) ($order['merchantId'] ?? ''),
            'raw_updated_at' => $order['createdAt'] ?? $order['updatedAt'] ?? null,
            'payload' => $order,
        ];
    }

    /**
     * @param mixed $payload
     */
    private function extractProviderError(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        return $payload['message']
            ?? Arr::get($payload, 'error.message')
            ?? Arr::get($payload, 'details.0.message')
            ?? null;
    }
}
