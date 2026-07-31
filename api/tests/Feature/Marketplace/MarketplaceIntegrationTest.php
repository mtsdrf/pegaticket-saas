<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\MarketplaceOrder;
use App\Models\Marketplace\MarketplaceEvent;
use App\Models\Marketplace\MarketplaceIntegration;
use App\Models\Marketplace\MarketplaceMerchant;
use App\Models\Order\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class MarketplaceIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('marketplace-user@test.com');
        $this->grantPermission('api-access', 'read');
        $this->grantPermission('api-access', 'create');
        $this->grantPermission('api-access', 'update');
        $this->grantPermission('storefront', 'update');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    private function ifoodSignature(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
    }

    #[Test]
    public function it_creates_syncs_polls_and_performs_marketplace_actions(): void
    {
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, [
            'sku' => 'IFOOD-SKU-1',
            'name' => 'Produto iFood',
            'price' => 42.5,
        ]);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants' => Http::response([
                [
                    'id' => 'merchant-1',
                    'name' => 'Loja Centro',
                    'active' => true,
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/events/v1.0/events:polling' => Http::response([
                [
                    'id' => 'event-1',
                    'code' => 'PLC',
                    'fullCode' => 'PLACED',
                    'merchantId' => 'merchant-1',
                    'createdAt' => '2026-07-25T10:15:00Z',
                    'metadata' => [
                        'orderId' => 'order-1',
                        'merchantId' => 'merchant-1',
                    ],
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/events/v1.0/events/acknowledgment' => Http::response([], 202),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-1' => Http::response([
                'id' => 'order-1',
                'displayId' => '98765',
                'orderNumber' => '00098765',
                'merchantId' => 'merchant-1',
                'orderState' => 'CONFIRMED',
                'customer' => [
                    'name' => 'Maria',
                    'phoneNumber' => '11999998888',
                ],
                'delivery' => [
                    'deliveryAddress' => [
                        'streetName' => 'Rua das Flores',
                        'streetNumber' => '100',
                        'district' => 'Centro',
                        'city' => 'Sao Paulo',
                        'state' => 'Sao Paulo',
                        'stateCode' => 'SP',
                        'postalCode' => '01001000',
                    ],
                ],
                'items' => [
                    [
                        'externalCode' => 'IFOOD-SKU-1',
                        'name' => 'Produto iFood',
                        'quantity' => 1,
                        'unitPrice' => 42.5,
                    ],
                ],
                'total' => ['orderAmount' => 42.5],
                'createdAt' => '2026-07-25T10:16:00Z',
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-1/confirm' => Http::response([
                'status' => 'accepted',
            ], 202),
        ]);

        $create = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood principal',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-1',
            'client_secret' => 'client-secret-1',
            'authorization_code' => 'auth-code-1',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.provider', 'ifood');

        $integrationUuid = $create->json('data.uuid');

        $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/sync-merchants")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.merchants');

        $poll = $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/poll");

        $poll->assertStatus(200)
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.acknowledged', 1);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/events")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $orders = $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/orders");

        $orders->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Maria')
            ->assertJsonPath('data.0.internal_order.origin', 'ifood');

        $orderUuid = MarketplaceOrder::query()->value('uuid');
        $internalOrder = Order::query()->first();
        $this->assertNotNull($internalOrder);
        $this->assertSame('ifood', $internalOrder->origin);

        $this->auth()
            ->postJson("/api/v1/marketplace/orders/{$orderUuid}/actions", [
                'action' => 'confirm',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'succeeded');

        Http::assertSentCount(6);
    }

    #[Test]
    public function it_receives_ifood_webhook_and_materializes_the_external_order(): void
    {
        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-webhook-1' => Http::response([
                'id' => 'order-webhook-1',
                'displayId' => '12345',
                'merchantId' => 'merchant-hook',
                'orderState' => 'PLACED',
                'customer' => ['name' => 'Joao'],
                'total' => ['orderAmount' => 59.9],
                'createdAt' => '2026-07-25T11:00:00Z',
            ], 200),
        ]);

        $create = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood webhook',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-hook',
            'client_secret' => 'client-secret-hook',
            'authorization_code' => 'auth-code-hook',
        ]);

        $integrationUuid = $create->json('data.uuid');

        $payload = [[
            'id' => 'event-hook-1',
            'code' => 'PLC',
            'fullCode' => 'PLACED',
            'merchantId' => 'merchant-hook',
            'createdAt' => '2026-07-25T11:00:00Z',
            'metadata' => [
                'orderId' => 'order-webhook-1',
                'merchantId' => 'merchant-hook',
            ],
        ]];

        $this->withHeaders([
            'X-IFood-Signature' => $this->ifoodSignature($payload, 'client-secret-hook'),
        ])->postJson("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $payload)->assertStatus(200)
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('marketplace_events', [
            'external_event_id' => 'event-hook-1',
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('marketplace_orders', [
            'external_id' => 'order-webhook-1',
            'customer_name' => 'Joao',
        ]);

        $show = $this->auth()->getJson('/api/v1/marketplace/integrations');
        $generatedWebhookUrl = $show->json('data.0.generated_webhook_url');
        $this->assertStringContainsString("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $generatedWebhookUrl);
    }

    #[Test]
    public function it_returns_merchant_status_creates_interruptions_and_syncs_opening_hours(): void
    {
        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants' => Http::response([
                [
                    'id' => 'merchant-availability-1',
                    'name' => 'Loja Operacional',
                    'active' => true,
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants/merchant-availability-1/status' => Http::response([
                [
                    'operation' => 'DELIVERY',
                    'salesChannel' => 'MARKETPLACE',
                    'available' => true,
                    'state' => 'OK',
                    'message' => 'Pronta para pedidos',
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants/merchant-availability-1/interruptions' => Http::sequence()
                ->push([
                    [
                        'id' => 'pause-1',
                        'description' => 'Manutencao rapida',
                        'start' => '2026-07-25T14:00:00Z',
                        'end' => '2026-07-25T14:30:00Z',
                    ],
                ], 200)
                ->push([
                    'id' => 'pause-1',
                    'description' => 'Manutencao rapida',
                    'start' => '2026-07-25T14:00:00Z',
                    'end' => '2026-07-25T14:30:00Z',
                ], 202),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants/merchant-availability-1/interruptions/pause-1' => Http::response([], 204),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants/merchant-availability-1/opening-hours' => Http::response([], 200),
        ]);

        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood disponibilidade',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-status',
            'client_secret' => 'client-secret-status',
            'authorization_code' => 'auth-code-status',
        ])->json('data.uuid');

        $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/sync-merchants")
            ->assertStatus(200);

        $this->auth()->putJson('/api/v1/store-settings/business-hours', [
            'days' => [
                ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'],
                ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '13:00', 'closes_at' => '18:00'],
                ['day_of_week' => 5, 'is_closed' => false, 'opens_at' => '18:00', 'closes_at' => '02:00'],
            ],
        ])->assertStatus(200);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/merchant-status")
            ->assertStatus(200)
            ->assertJsonPath('data.merchant.external_id', 'merchant-availability-1')
            ->assertJsonPath('data.status.0.available', true)
            ->assertJsonPath('data.interruptions.0.id', 'pause-1');

        $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/interruptions", [
                'description' => 'Manutencao rapida',
                'duration' => 30,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interruption.id', 'pause-1');

        $sync = $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/opening-hours/sync");

        $sync->assertStatus(200)
            ->assertJsonPath('data.shifts_count', 4)
            ->assertJsonPath('data.shifts.0.dayOfWeek', 'MONDAY')
            ->assertJsonPath('data.shifts.2.dayOfWeek', 'FRIDAY')
            ->assertJsonPath('data.shifts.3.dayOfWeek', 'SATURDAY');

        $this->auth()
            ->deleteJson("/api/v1/marketplace/integrations/{$integrationUuid}/interruptions/pause-1")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PUT' || $request->url() !== 'https://merchant-api.ifood.com.br/merchant/v1.0/merchants/merchant-availability-1/opening-hours') {
                return false;
            }

            $payload = $request->data();

            return isset($payload['shifts'])
                && count($payload['shifts']) === 4
                && $payload['shifts'][0]['dayOfWeek'] === 'MONDAY'
                && $payload['shifts'][0]['duration'] === 240
                && $payload['shifts'][3]['dayOfWeek'] === 'SATURDAY'
                && $payload['shifts'][3]['start'] === '00:00:00'
                && $payload['shifts'][3]['duration'] === 120;
        });
    }

    #[Test]
    public function it_rejects_ifood_webhook_with_invalid_signature(): void
    {
        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood assinatura',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-signature',
            'client_secret' => 'client-secret-signature',
            'authorization_code' => 'auth-code-signature',
        ])->json('data.uuid');

        $payload = [[
            'id' => 'event-invalid-signature',
            'code' => 'PLC',
            'fullCode' => 'PLACED',
        ]];

        $this->withHeaders([
            'X-IFood-Signature' => 'invalid-signature',
        ])->postJson("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $payload)
            ->assertStatus(401)
            ->assertJsonPath('code', 'MARKETPLACE_INVALID_SIGNATURE');
    }

    #[Test]
    public function it_answers_keepalive_presence_requests_with_online_merchants(): void
    {
        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants' => Http::response([
                [
                    'id' => 'merchant-keepalive-1',
                    'name' => 'Loja Online',
                    'active' => true,
                ],
                [
                    'id' => 'merchant-keepalive-2',
                    'name' => 'Loja Offline',
                    'active' => false,
                ],
            ], 200),
        ]);

        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood keepalive',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-keepalive',
            'client_secret' => 'client-secret-keepalive',
            'authorization_code' => 'auth-code-keepalive',
        ])->json('data.uuid');

        $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/sync-merchants")
            ->assertStatus(200);

        $payload = [
            'code' => 'KEEPALIVE',
            'fullCode' => 'KEEPALIVE',
            'id' => 'keepalive-request-1',
            'merchantIds' => ['merchant-keepalive-1', 'merchant-keepalive-2', 'merchant-missing'],
        ];

        $this->withHeaders([
            'X-IFood-Signature' => $this->ifoodSignature($payload, 'client-secret-keepalive'),
        ])->postJson("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $payload)
            ->assertStatus(202)
            ->assertJson([
                'merchantIds' => ['merchant-keepalive-1'],
            ]);
    }

    #[Test]
    public function it_allows_manual_retry_of_internal_import_after_product_mapping_is_created(): void
    {
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);

        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-retry-1' => Http::response([
                'id' => 'order-retry-1',
                'displayId' => '54321',
                'merchantId' => 'merchant-retry',
                'orderState' => 'PLACED',
                'customer' => ['name' => 'Cliente Retry'],
                'items' => [
                    [
                        'externalCode' => 'SKU-AINDA-NAO-EXISTE',
                        'name' => 'Produto pendente',
                        'quantity' => 1,
                        'unitPrice' => 30,
                    ],
                ],
                'total' => ['orderAmount' => 30],
                'createdAt' => '2026-07-25T12:00:00Z',
            ], 200),
        ]);

        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood retry',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-retry',
            'client_secret' => 'client-secret-retry',
            'authorization_code' => 'auth-code-retry',
        ])->json('data.uuid');

        $payload = [[
            'id' => 'event-retry-1',
            'code' => 'PLC',
            'fullCode' => 'PLACED',
            'merchantId' => 'merchant-retry',
            'createdAt' => '2026-07-25T12:00:00Z',
            'metadata' => [
                'orderId' => 'order-retry-1',
                'merchantId' => 'merchant-retry',
            ],
        ]];

        $this->withHeaders([
            'X-IFood-Signature' => $this->ifoodSignature($payload, 'client-secret-retry'),
        ])->postJson("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $payload)->assertStatus(200);

        $marketplaceOrder = MarketplaceOrder::query()->firstOrFail();
        $this->assertNull($marketplaceOrder->internal_order_id);
        $this->assertNotNull($marketplaceOrder->import_error_message);

        $product = $this->createProduct($this->tenant->id, [
            'sku' => 'SKU-AINDA-NAO-EXISTE',
            'name' => 'Produto pendente',
            'price' => 30,
        ]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()
            ->postJson("/api/v1/marketplace/orders/{$marketplaceOrder->uuid}/import")
            ->assertStatus(200)
            ->assertJsonPath('data.internal_order.origin', 'ifood');

        $this->assertDatabaseHas('marketplace_orders', [
            'uuid' => $marketplaceOrder->uuid,
        ]);
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function it_tracks_failed_events_and_allows_manual_reprocessing(): void
    {
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, [
            'sku' => 'IFOOD-RETRY-OK',
            'name' => 'Produto retry',
            'price' => 25,
        ]);
        $this->stockEntry($this->tenant->id, $product, $location, 5);

        $shouldFailOrderFetch = true;

        Http::fake(function (Request $request) use (&$shouldFailOrderFetch) {
            $url = $request->url();

            if ($url === 'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token') {
                return Http::response([
                    'accessToken' => 'ifood_access_token',
                    'refreshToken' => 'ifood_refresh_token',
                    'expiresIn' => 21600,
                ], 200);
            }

            if ($url === 'https://merchant-api.ifood.com.br/order/v1.0/orders/order-failed-1') {
                if ($shouldFailOrderFetch) {
                    return Http::response(['message' => 'temporary failure'], 500);
                }

                return Http::response([
                    'id' => 'order-failed-1',
                    'displayId' => '99881',
                    'merchantId' => 'merchant-failed',
                    'orderState' => 'PLACED',
                    'customer' => ['name' => 'Cliente Operacional'],
                    'items' => [
                        [
                            'externalCode' => 'IFOOD-RETRY-OK',
                            'name' => 'Produto retry',
                            'quantity' => 1,
                            'unitPrice' => 25,
                        ],
                    ],
                    'total' => ['orderAmount' => 25],
                    'createdAt' => '2026-07-25T13:00:00Z',
                ], 200);
            }

            return Http::response([], 200);
        });

        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood operacional',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-oper',
            'client_secret' => 'client-secret-oper',
            'authorization_code' => 'auth-code-oper',
        ])->json('data.uuid');

        $payload = [[
            'id' => 'event-failed-1',
            'code' => 'PLC',
            'fullCode' => 'PLACED',
            'merchantId' => 'merchant-failed',
            'createdAt' => '2026-07-25T13:00:00Z',
            'metadata' => [
                'orderId' => 'order-failed-1',
                'merchantId' => 'merchant-failed',
            ],
        ]];

        $this->withHeaders([
            'X-IFood-Signature' => $this->ifoodSignature($payload, 'client-secret-oper'),
        ])->postJson("/api/v1/webhooks/marketplace/ifood/{$integrationUuid}", $payload)->assertStatus(200);

        $event = $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/events")
            ->assertStatus(200)
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.processing_attempts', 1)
            ->json('data.0');

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/operations-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.events_failed', 1)
            ->assertJsonPath('data.orders_total', 0);

        $shouldFailOrderFetch = false;

        $this->auth()
            ->postJson("/api/v1/marketplace/events/{$event['uuid']}/retry")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.processing_attempts', 2);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/operations-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.events_failed', 0)
            ->assertJsonPath('data.orders_imported', 1);

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function it_previews_and_starts_catalog_sync_for_ifood(): void
    {
        $this->createProduct($this->tenant->id, [
            'sku' => 'CAT-ITEM-1',
            'name' => 'Hamburguer Artesanal',
            'price' => 32.5,
            'is_available' => true,
        ]);

        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/merchant/v1.0/merchants' => Http::response([
                [
                    'id' => 'merchant-catalog-1',
                    'name' => 'Loja Catálogo',
                    'active' => true,
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/catalog/v2.0/merchants/merchant-catalog-1/categories?include_items=true' => Http::response([], 200),
            'https://merchant-api.ifood.com.br/catalog/v2.0/merchants/merchant-catalog-1/categories' => Http::response([
                'batchId' => 'batch-category-1',
            ], 202),
            'https://merchant-api.ifood.com.br/catalog/v2.0/merchants/merchant-catalog-1/items' => Http::response([
                'batchId' => 'batch-item-1',
            ], 202),
            'https://merchant-api.ifood.com.br/catalog/v2.0/merchants/merchant-catalog-1/batch/batch-category-1' => Http::response([
                'batchStatus' => 'COMPLETED',
                'results' => [
                    ['result' => 'SUCCESS'],
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/catalog/v2.0/merchants/merchant-catalog-1/batch/batch-item-1' => Http::response([
                'batchStatus' => 'COMPLETED',
                'results' => [
                    ['result' => 'SUCCESS'],
                ],
            ], 200),
        ]);

        $integrationUuid = $this->auth()->postJson('/api/v1/marketplace/integrations', [
            'provider' => 'ifood',
            'name' => 'iFood catálogo',
            'environment' => 'sandbox',
            'is_active' => true,
            'client_id' => 'client-id-catalog',
            'client_secret' => 'client-secret-catalog',
            'authorization_code' => 'auth-code-catalog',
        ])->json('data.uuid');

        $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/sync-merchants")
            ->assertStatus(200);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/catalog/preview")
            ->assertStatus(200)
            ->assertJsonPath('data.merchant.name', 'Loja Catálogo')
            ->assertJsonPath('data.supported_features.0', 'categories')
            ->assertJsonPath('data.supported_features.2', 'complement_groups')
            ->assertJsonPath('data.supported_features.3', 'complements')
            ->assertJsonPath('data.pending_features.0', 'combos')
            ->assertJsonPath('data.categories_total', 1)
            ->assertJsonPath('data.items_total', 1)
            ->assertJsonPath('data.items.0.product_name', 'Hamburguer Artesanal');

        $sync = $this->auth()
            ->postJson("/api/v1/marketplace/integrations/{$integrationUuid}/catalog/sync")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->json('data');

        $this->auth()
            ->postJson("/api/v1/marketplace/catalog/syncs/{$sync['uuid']}/refresh")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.success_count', 2);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integrationUuid}/catalog/syncs")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.items.0.batch_id', 'batch-category-1');

        $this->assertDatabaseHas('marketplace_catalog_mappings', [
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'category',
            'entity_key' => 'mk-cat-' . (string) \App\Models\Product\Product::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('name', 'Hamburguer Artesanal')
                ->with('productType.productCategory')
                ->firstOrFail()
                ->productType
                ?->productCategory
                ?->uuid,
        ]);

        $this->assertDatabaseHas('marketplace_catalog_mappings', [
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'item',
            'entity_key' => 'mk-item-' . $this->createUuidMatcher('Hamburguer Artesanal'),
        ]);
    }

    #[Test]
    public function it_paginates_filters_and_shows_marketplace_orders_with_event_timeline(): void
    {
        $integration = MarketplaceIntegration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'ifood',
            'name' => 'iFood fila',
            'environment' => 'sandbox',
            'auth_mode' => 'centralized',
            'status' => 'connected',
            'is_active' => true,
        ]);

        $merchant = MarketplaceMerchant::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'external_id' => 'merchant-filter-1',
            'name' => 'Loja Fila',
            'is_active' => true,
        ]);

        $pendingOrder = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'ext-pending-1',
            'display_id' => 'PEND-1',
            'status' => 'PLACED',
            'customer_name' => 'Cliente Pendente',
            'total_amount' => 19.9,
            'payload' => ['id' => 'ext-pending-1'],
            'last_synced_at' => now(),
        ]);

        $errorOrder = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'ext-error-1',
            'display_id' => 'ERR-1',
            'status' => 'CONFIRMED',
            'customer_name' => 'Cliente Erro',
            'total_amount' => 39.9,
            'payload' => ['id' => 'ext-error-1'],
            'import_error_message' => 'SKU não encontrado.',
            'last_synced_at' => now()->subMinute(),
        ]);

        MarketplaceEvent::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_event_id' => 'evt-pending-1',
            'external_order_id' => 'ext-pending-1',
            'event_type' => 'PLC',
            'event_full_code' => 'PLACED',
            'payload' => ['id' => 'evt-pending-1'],
            'status' => 'processed',
            'occurred_at' => now()->subMinutes(2),
            'acknowledged_at' => now()->subMinutes(2),
            'processed_at' => now()->subMinutes(2),
        ]);

        MarketplaceEvent::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_event_id' => 'evt-pending-2',
            'external_order_id' => 'ext-pending-1',
            'event_type' => 'CFM',
            'event_full_code' => 'CONFIRMED',
            'payload' => ['id' => 'evt-pending-2'],
            'status' => 'processed',
            'occurred_at' => now()->subMinute(),
            'acknowledged_at' => now()->subMinute(),
            'processed_at' => now()->subMinute(),
        ]);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integration->uuid}/orders?queue_status=pending_import&search=pendente")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $pendingOrder->uuid)
            ->assertJsonPath('data.0.queue_status', 'pending_import')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integration->uuid}/orders?queue_status=import_error")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $errorOrder->uuid)
            ->assertJsonPath('data.0.queue_status', 'import_error');

        $this->auth()
            ->getJson("/api/v1/marketplace/orders/{$pendingOrder->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $pendingOrder->uuid)
            ->assertJsonPath('data.events_count', 2)
            ->assertJsonPath('data.events.0.event_full_code', 'CONFIRMED')
            ->assertJsonPath('data.events.1.event_full_code', 'PLACED');
    }

    #[Test]
    public function it_returns_marketplace_sla_aggregates_in_operations_summary(): void
    {
        $integration = MarketplaceIntegration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'ifood',
            'name' => 'iFood SLA',
            'environment' => 'sandbox',
            'auth_mode' => 'centralized',
            'status' => 'connected',
            'is_active' => true,
        ]);

        $merchant = MarketplaceMerchant::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'external_id' => 'merchant-sla-1',
            'name' => 'Loja SLA',
            'is_active' => true,
        ]);

        MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'pending-attention',
            'display_id' => 'PA-1',
            'status' => 'PLACED',
            'customer_name' => 'Cliente Atenção',
            'total_amount' => 19.9,
            'payload' => ['id' => 'pending-attention'],
            'last_synced_at' => now()->subMinutes(7),
        ]);

        MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'pending-critical',
            'display_id' => 'PC-1',
            'status' => 'PLACED',
            'customer_name' => 'Cliente Crítico',
            'total_amount' => 24.9,
            'payload' => ['id' => 'pending-critical'],
            'last_synced_at' => now()->subMinutes(21),
        ]);

        MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'import-error',
            'display_id' => 'IE-1',
            'status' => 'CONFIRMED',
            'customer_name' => 'Cliente Erro',
            'total_amount' => 39.9,
            'payload' => ['id' => 'import-error'],
            'import_error_message' => 'SKU não encontrado.',
            'last_synced_at' => now()->subMinutes(34),
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);

        $internalOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'codigo' => 'IFOOD-SLA-1',
            'is_installment' => false,
            'total_amount' => 50,
            'delivery_fee' => 0,
            'service_fee' => 0,
            'discount_amount' => 0,
            'cashback_redeemed_amount' => 0,
            'paid_amount' => 0,
            'is_paid' => false,
            'is_delivered' => false,
            'status' => 'confirmed',
            'origin' => 'ifood',
            'stock_reserved' => false,
            'is_out_for_delivery' => false,
        ]);

        MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'internal_order_id' => $internalOrder->id,
            'external_id' => 'imported-stale',
            'display_id' => 'IS-1',
            'status' => 'CONFIRMED',
            'customer_name' => 'Cliente Importado',
            'total_amount' => 29.9,
            'payload' => ['id' => 'imported-stale'],
            'imported_at' => now()->subMinutes(65),
            'last_synced_at' => now()->subMinutes(61),
        ]);

        $this->auth()
            ->getJson("/api/v1/marketplace/integrations/{$integration->uuid}/operations-summary")
            ->assertStatus(200)
            ->assertJsonPath('data.orders_pending_import', 2)
            ->assertJsonPath('data.orders_with_import_error', 1)
            ->assertJsonPath('data.orders_pending_import_attention', 1)
            ->assertJsonPath('data.orders_pending_import_critical', 1)
            ->assertJsonPath('data.orders_imported_without_recent_signal', 1)
            ->assertJsonPath('data.oldest_pending_import_minutes', 21)
            ->assertJsonPath('data.oldest_import_error_minutes', 34)
            ->assertJsonPath('data.oldest_imported_without_signal_minutes', 61)
            ->assertJsonPath('data.needs_attention', true);
    }

    #[Test]
    public function it_refreshes_an_external_order_and_retries_internal_import(): void
    {
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, [
            'sku' => 'IFOOD-REFRESH-1',
            'name' => 'Produto refresh',
            'price' => 31.5,
        ]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-refresh-1' => Http::response([
                'id' => 'order-refresh-1',
                'displayId' => 'RF-1',
                'merchantId' => 'merchant-refresh-1',
                'orderState' => 'CONFIRMED',
                'customer' => ['name' => 'Cliente Refresh', 'phoneNumber' => '11999997777'],
                'delivery' => [
                    'deliveryAddress' => [
                        'streetName' => 'Rua Nova',
                        'streetNumber' => '10',
                        'district' => 'Centro',
                        'city' => 'Sao Paulo',
                        'state' => 'Sao Paulo',
                        'stateCode' => 'SP',
                        'postalCode' => '01001000',
                    ],
                ],
                'items' => [
                    [
                        'externalCode' => 'IFOOD-REFRESH-1',
                        'name' => 'Produto refresh',
                        'quantity' => 1,
                        'unitPrice' => 31.5,
                    ],
                ],
                'total' => ['orderAmount' => 31.5],
                'createdAt' => '2026-07-25T15:00:00Z',
            ], 200),
        ]);

        $integration = MarketplaceIntegration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'ifood',
            'name' => 'iFood refresh',
            'environment' => 'sandbox',
            'auth_mode' => 'centralized',
            'status' => 'connected',
            'is_active' => true,
            'client_id' => 'client-id-refresh',
            'client_secret' => 'client-secret-refresh',
            'authorization_code' => 'auth-code-refresh',
        ]);

        $merchant = MarketplaceMerchant::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'external_id' => 'merchant-refresh-1',
            'name' => 'Loja Refresh',
            'is_active' => true,
        ]);

        $marketplaceOrder = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'order-refresh-1',
            'display_id' => 'RF-1',
            'status' => 'PLACED',
            'customer_name' => 'Cliente Refresh',
            'total_amount' => 31.5,
            'payload' => ['id' => 'order-refresh-1'],
            'import_error_message' => 'Produto ainda não mapeado.',
            'last_synced_at' => now()->subMinutes(5),
        ]);

        $this->auth()
            ->postJson("/api/v1/marketplace/orders/{$marketplaceOrder->uuid}/refresh")
            ->assertStatus(200)
            ->assertJsonPath('data.queue_status', 'imported')
            ->assertJsonPath('data.internal_order.origin', 'ifood');

        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function it_lists_cancellation_reasons_and_sends_cancel_action(): void
    {
        Http::fake([
            'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token' => Http::response([
                'accessToken' => 'ifood_access_token',
                'refreshToken' => 'ifood_refresh_token',
                'expiresIn' => 21600,
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-cancel-1/cancellationReasons' => Http::response([
                [
                    'code' => 'CUSTOMER_REQUEST',
                    'description' => 'Cliente solicitou o cancelamento',
                ],
                [
                    'code' => 'OUT_OF_STOCK',
                    'description' => 'Item indisponível',
                ],
            ], 200),
            'https://merchant-api.ifood.com.br/order/v1.0/orders/order-cancel-1/cancel' => Http::response([
                'status' => 'accepted',
            ], 202),
        ]);

        $integration = MarketplaceIntegration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'ifood',
            'name' => 'iFood cancelamento',
            'environment' => 'sandbox',
            'auth_mode' => 'centralized',
            'status' => 'connected',
            'is_active' => true,
            'client_id' => 'client-id-cancel',
            'client_secret' => 'client-secret-cancel',
            'authorization_code' => 'auth-code-cancel',
        ]);

        $merchant = MarketplaceMerchant::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'external_id' => 'merchant-cancel-1',
            'name' => 'Loja Cancelamento',
            'is_active' => true,
        ]);

        $marketplaceOrder = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'order-cancel-1',
            'display_id' => 'CANCEL-1',
            'status' => 'CONFIRMED',
            'customer_name' => 'Cliente Cancelamento',
            'total_amount' => 18.5,
            'payload' => ['id' => 'order-cancel-1'],
            'last_synced_at' => now(),
        ]);

        $this->auth()
            ->getJson("/api/v1/marketplace/orders/{$marketplaceOrder->uuid}/cancellation-reasons")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'CUSTOMER_REQUEST')
            ->assertJsonPath('data.0.description', 'Cliente solicitou o cancelamento');

        $this->auth()
            ->postJson("/api/v1/marketplace/orders/{$marketplaceOrder->uuid}/actions", [
                'action' => 'cancel',
                'code' => 'CUSTOMER_REQUEST',
                'reason' => 'Cliente pediu para cancelar via central.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'cancel')
            ->assertJsonPath('data.status', 'succeeded');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://merchant-api.ifood.com.br/order/v1.0/orders/order-cancel-1/cancel'
                && $request['merchantId'] === 'merchant-cancel-1'
                && $request['code'] === 'CUSTOMER_REQUEST'
                && $request['reason'] === 'Cliente pediu para cancelar via central.';
        });
    }

    #[Test]
    public function it_recovers_failed_events_and_pending_orders_with_console_command(): void
    {
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, [
            'sku' => 'IFOOD-RECOVER-1',
            'name' => 'Produto recover',
            'price' => 27,
        ]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token') {
                return Http::response([
                    'accessToken' => 'ifood_access_token',
                    'refreshToken' => 'ifood_refresh_token',
                    'expiresIn' => 21600,
                ], 200);
            }

            if ($url === 'https://merchant-api.ifood.com.br/order/v1.0/orders/order-recover-event-1') {
                return Http::response([
                    'id' => 'order-recover-event-1',
                    'displayId' => 'RCV-EVT-1',
                    'merchantId' => 'merchant-recover-1',
                    'orderState' => 'PLACED',
                    'customer' => ['name' => 'Cliente Recover Evento'],
                    'items' => [[
                        'externalCode' => 'IFOOD-RECOVER-1',
                        'name' => 'Produto recover',
                        'quantity' => 1,
                        'unitPrice' => 27,
                    ]],
                    'total' => ['orderAmount' => 27],
                    'createdAt' => '2026-07-25T16:00:00Z',
                ], 200);
            }

            if ($url === 'https://merchant-api.ifood.com.br/order/v1.0/orders/order-recover-pending-1') {
                return Http::response([
                    'id' => 'order-recover-pending-1',
                    'displayId' => 'RCV-PND-1',
                    'merchantId' => 'merchant-recover-1',
                    'orderState' => 'CONFIRMED',
                    'customer' => ['name' => 'Cliente Recover Pendente'],
                    'items' => [[
                        'externalCode' => 'IFOOD-RECOVER-1',
                        'name' => 'Produto recover',
                        'quantity' => 1,
                        'unitPrice' => 27,
                    ]],
                    'total' => ['orderAmount' => 27],
                    'createdAt' => '2026-07-25T16:05:00Z',
                ], 200);
            }

            return Http::response([], 200);
        });

        $integration = MarketplaceIntegration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'ifood',
            'name' => 'iFood recovery',
            'environment' => 'sandbox',
            'auth_mode' => 'centralized',
            'status' => 'connected',
            'is_active' => true,
            'client_id' => 'client-id-recover',
            'client_secret' => 'client-secret-recover',
            'authorization_code' => 'auth-code-recover',
            'last_polled_at' => now()->subMinutes(20),
            'last_error_at' => now()->subMinutes(5),
        ]);

        $merchant = MarketplaceMerchant::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'external_id' => 'merchant-recover-1',
            'name' => 'Loja Recovery',
            'is_active' => true,
        ]);

        $failedEvent = MarketplaceEvent::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_event_id' => 'event-recover-1',
            'external_order_id' => 'order-recover-event-1',
            'event_type' => 'PLC',
            'event_full_code' => 'PLACED',
            'payload' => [
                'id' => 'event-recover-1',
                'code' => 'PLC',
                'fullCode' => 'PLACED',
                'merchantId' => 'merchant-recover-1',
                'metadata' => [
                    'orderId' => 'order-recover-event-1',
                    'merchantId' => 'merchant-recover-1',
                ],
            ],
            'status' => 'failed',
            'processing_attempts' => 1,
            'error_message' => 'Falha temporária no fetch do pedido.',
            'occurred_at' => now()->subMinutes(12),
            'last_attempted_at' => now()->subMinutes(12),
        ]);

        $pendingOrder = MarketplaceOrder::create([
            'tenant_id' => $this->tenant->id,
            'integration_id' => $integration->id,
            'marketplace_merchant_id' => $merchant->id,
            'external_id' => 'order-recover-pending-1',
            'display_id' => 'RCV-PND-1',
            'status' => 'PLACED',
            'customer_name' => 'Cliente Recover Pendente',
            'total_amount' => 27,
            'payload' => ['id' => 'order-recover-pending-1'],
            'import_error_message' => 'Produto ainda não importado.',
            'last_synced_at' => now()->subMinutes(30),
        ]);

        $this->artisan('marketplace:recover-ifood', [
            '--limit' => 5,
            '--events' => 5,
            '--orders' => 5,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('marketplace_events', [
            'id' => $failedEvent->id,
            'status' => 'processed',
            'processing_attempts' => 2,
        ]);

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $pendingOrder->id,
            'external_id' => 'order-recover-pending-1',
        ]);

        $this->assertSame(2, Order::query()->count());

        $pendingOrder->refresh();
        $this->assertNotNull($pendingOrder->internal_order_id);
    }

    private function createUuidMatcher(string $productName): string
    {
        return (string) \App\Models\Product\Product::query()->where('tenant_id', $this->tenant->id)->where('name', $productName)->value('uuid');
    }
}
