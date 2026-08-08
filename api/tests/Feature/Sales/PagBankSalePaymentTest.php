<?php

namespace Tests\Feature\Sales;

use App\Models\Finance\PlatformFinanceSettings;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\WebhookEvent;
use App\Models\Tenant\TenantPagBankConnection;
use App\Models\Tenant\TenantSettings;
use App\Services\Sale\SalePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\Support\PagBankTestCards;
use Tests\TestCase;

/**
 * Cobrança Pix de venda via PagBankPaymentProvider (integração real contra
 * a API de Vendas do PagBank, confirmada via documentação oficial
 * developer.pagbank.com.br). Client HTTP mockado (Http::fake) — nenhuma
 * chamada de rede real acontece em teste.
 */
class PagBankSalePaymentTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesStorefrontFixtures;
    use PagBankTestCards;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);
        Config::set('services.payments.sale_provider', 'pagbank');
        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-pagbank-token');

        $this->setUpTenantScopedUser('pagbank-order-payment-user@test.com');
        File::delete(storage_path('logs/pagbank-transactions.log'));
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createConfirmedOrder(float $price = 40, int $qty = 1): array
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => $qty],
            ],
        ])->json('data');

        // Venda manual não parcelada nasce já paga — desfaz aqui pra
        // simular uma venda ainda não paga (pré-condição de
        // createPixChargeForOrder()).
        Sale::where('uuid', $order['uuid'])->update(['is_paid' => false, 'paid_at' => null]);

        return $order;
    }

    private function configureTenantPagBankDirect(string $token, string $environment = 'sandbox'): void
    {
        TenantSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'payment_receiving_method' => 'pagbank_token',
                'pagbank_integration_mode' => 'manual_token',
                'pagbank_environment' => $environment,
                'pagbank_access_token' => $token,
            ]
        );
    }

    private function createEnabledPagBankConnection(string $accountId, string $status = TenantPagBankConnection::STATUS_ENABLED): void
    {
        TenantPagBankConnection::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'provider' => 'pagbank',
            'status' => $status,
            'account_id' => $accountId,
            'environment' => 'sandbox',
            'connected_at' => now(),
        ]);
    }

    private function configureSplitAccounts(string $platformAccountId, string $tenantAccountId, float $platformFee = 5): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => $platformFee,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
            'pagbank_primary_account_id' => $platformAccountId,
        ]);

        TenantSettings::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'pagbank_receiver_account_id' => $tenantAccountId,
            ]
        );
    }

    #[Test]
    public function creates_a_pix_order_via_pagbank_and_stores_the_qr_code(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328',
                'reference_id' => 'sale-ref',
                'qr_codes' => [[
                    'id' => 'QRCO_1',
                    'text' => '00020126...copia-e-cola...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge");

        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328',
            'status' => 'pending',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/orders'
                && $request->hasHeader('Authorization', 'Bearer fake-pagbank-token')
                && $request['customer']['tax_id'] === '12345678909'
                && $request['qr_codes'][0]['amount']['value'] === 8000
                && $request['notification_urls'][0] === url('/api/v1/webhooks/payments/pagbank');
        });
    }

    #[Test]
    public function logs_orders_failed_metric_when_pagbank_rejects_the_order_creation(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response(['error_messages' => [['error' => 'invalid_parameter']]], 400),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'failed');

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_orders_total"', $contents);
        $this->assertStringContainsString('"metric":"pagbank_orders_failed"', $contents);
    }

    #[Test]
    public function logs_split_failed_metric_when_the_platform_is_configured_but_the_tenant_has_no_receiving_account(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 5,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
            'pagbank_primary_account_id' => 'ACCO_PLATFORM_ONLY',
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_NOSPLIT_1',
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...']],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_split_failed"', $contents);
    }

    #[Test]
    public function returns_pagbank_checkout_config_with_public_key_and_three_ds_session(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_1',
                'public_key' => 'PAGBANK_PUBLIC_KEY_SANDBOX',
            ], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'PAGBANK_3DS_SESSION_SANDBOX',
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 30, qty: 1);

        $response = $this->auth()->getJson("/api/v1/sales/{$order['uuid']}/payment-checkout-config");

        $response->assertStatus(200)
            ->assertJsonPath('data.provider', 'pagbank')
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.environment', 'SANDBOX')
            ->assertJsonPath('data.public_key', 'PAGBANK_PUBLIC_KEY_SANDBOX')
            ->assertJsonPath('data.three_ds_session', 'PAGBANK_3DS_SESSION_SANDBOX');
    }

    #[Test]
    public function tenant_pagbank_token_overrides_the_global_fallback_for_order_checkout_and_charge(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');

        Http::fake([
            'api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_TENANT',
                'public_key' => 'TENANT_PUBLIC_KEY',
            ], 201),
            'sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'TENANT_3DS_SESSION',
            ], 200),
            'api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_TENANT_1',
                'qr_codes' => [[
                    'id' => 'QRCO_TENANT_1',
                    'text' => '00020126...tenant...6304ABCD',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 35, qty: 1);

        $this->auth()
            ->getJson("/api/v1/sales/{$order['uuid']}/payment-checkout-config")
            ->assertStatus(200)
            ->assertJsonPath('data.environment', 'PROD')
            ->assertJsonPath('data.public_key', 'TENANT_PUBLIC_KEY');

        $this->auth()
            ->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(function ($request) {
            return in_array($request->url(), [
                'https://api.pagseguro.com/public-keys',
                'https://sdk.pagseguro.com/checkout-sdk/sessions',
                'https://api.pagseguro.com/orders',
            ], true)
                && $request->hasHeader('Authorization', 'Bearer tenant-token-123');
        });
    }

    #[Test]
    public function split_configuration_uses_the_platform_token_and_builds_pix_split_with_custody(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_TENANT_1', 7.5);

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_PLATFORM',
                'public_key' => 'PLATFORM_PUBLIC_KEY',
            ], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'PLATFORM_3DS_SESSION',
            ], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_SPLIT_1',
                'charges' => [[
                    'links' => [[
                        'rel' => 'SPLIT',
                        'href' => 'https://sandbox.api.pagseguro.com/splits/SPLI_SPLIT_1',
                    ]],
                ]],
                'qr_codes' => [[
                    'id' => 'QRCO_SPLIT_1',
                    'text' => '00020126...split...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        // Este teste cobre especificamente a retenção da taxa FIXA da
        // plataforma (platform_fee_fixed_amount), pré-existente à taxa de
        // serviço PegaTicket (10%/mínimo) — força fee_payer='buyer' pra
        // isolar o cenário (produtor não paga taxa de serviço aqui, só a
        // fixa). O caso 'producer' (as duas taxas somadas no split) tem
        // teste dedicado: split_retains_the_platform_service_fee_when_the_producer_pays_it.
        Sale::where('uuid', $order['uuid'])->update(['platform_fee_payer_snapshot' => 'buyer']);

        $this->auth()
            ->getJson("/api/v1/sales/{$order['uuid']}/payment-checkout-config")
            ->assertStatus(200)
            ->assertJsonPath('data.environment', 'SANDBOX')
            ->assertJsonPath('data.public_key', 'PLATFORM_PUBLIC_KEY');

        $this->auth()
            ->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $payment = Payment::query()->where('provider_charge_id', 'ORDE_SPLIT_1')->firstOrFail();
        $this->assertSame('SPLI_SPLIT_1', $payment->metadata['split_id'] ?? null);
        $this->assertNotEmpty($payment->metadata['split_release_scheduled'] ?? null);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sandbox.api.pagseguro.com/orders') {
                return false;
            }

            $receivers = $request['qr_codes'][0]['splits']['receivers'] ?? [];

            return $request->hasHeader('Authorization', 'Bearer fake-pagbank-token')
                && ($request['qr_codes'][0]['splits']['method'] ?? null) === 'FIXED'
                && ($receivers[0]['account']['id'] ?? null) === 'ACCO_PLATFORM_1'
                && ($receivers[0]['amount']['value'] ?? null) === 750
                && ($receivers[1]['account']['id'] ?? null) === 'ACCO_TENANT_1'
                && ($receivers[1]['amount']['value'] ?? null) === 7250
                && ($receivers[1]['configurations']['custody']['apply'] ?? null) === true
                && ! empty($receivers[1]['configurations']['custody']['release']['scheduled'] ?? null);
        });
    }

    /**
     * @return array<string, array{0: array{brand:string,number:string,cvv:string,expiration:string,encrypted:string}}>
     */
    public static function sandboxCreditCardsProvider(): array
    {
        $datasets = [];

        foreach (self::pagBankSandboxCards() as $card) {
            $datasets['sandbox_card_'.strtolower($card['brand'])] = [$card];
        }

        return $datasets;
    }

    #[Test]
    #[DataProvider('sandboxCreditCardsProvider')]
    public function creates_a_credit_card_order_via_pagbank_using_the_pagbank_sandbox_cards(array $card): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_CARD_1',
                'charges' => [[
                    'id' => 'CHAR_CARD_1',
                    'status' => 'PAID',
                    'payment_response' => [
                        'code' => '20000',
                        'message' => 'SUCESSO',
                    ],
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                        'installments' => 3,
                        'capture' => true,
                        'card' => [
                            'brand' => 'visa',
                            'first_digits' => '411111',
                            'last_digits' => '1111',
                        ],
                    ],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 75, qty: 1);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => $card['encrypted'],
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 3,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.method', 'credit_card');

        Http::assertSent(function ($request) use ($card) {
            $description = (string) ($request['charges'][0]['description'] ?? '');
            $itemName = (string) ($request['items'][0]['name'] ?? '');

            return $request->url() === 'https://sandbox.api.pagseguro.com/orders'
                && $request['customer']['tax_id'] === '12345678909'
                && $request['customer']['email'] === 'mreisf.contato@gmail.com'
                && $request['customer']['phones'][0]['number'] === '999999999'
                && $itemName !== ''
                && $request['charges'][0]['payment_method']['type'] === 'CREDIT_CARD'
                && $request['charges'][0]['payment_method']['installments'] === 3
                && $request['charges'][0]['payment_method']['card']['encrypted'] === $card['encrypted']
                && $request['charges'][0]['payment_method']['holder']['tax_id'] === '65544332211'
                && str_starts_with($description, 'Compra ')
                && str_contains($description, ' + Test Tenant');
        });

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_CARD_1',
            'method' => 'credit_card',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'confirmed',
            'is_paid' => true,
        ]);
    }

    #[Test]
    public function captures_the_real_pagbank_fee_and_computes_a_positive_platform_net_revenue(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_FEE_1',
                'charges' => [[
                    'id' => 'CHAR_FEE_1',
                    'status' => 'PAID',
                    // TODO: nome de campo não confirmado na doc oficial
                    // (ver PagBankPaymentProvider::ACTUAL_FEE_CANDIDATE_PATHS)
                    // — usado aqui só como fixture até a doc confirmar.
                    'fees' => ['value' => 150],
                    'payment_method' => ['type' => 'CREDIT_CARD'],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ])->assertStatus(201);

        $sale = Sale::where('uuid', $order['uuid'])->firstOrFail();

        $this->assertSame('1.50', (string) $sale->pagbank_fee_actual);
        $this->assertNotNull($sale->pagbank_fee_actual_captured_at);
        $this->assertSame('4.00', (string) $sale->platform_fee_total_amount);
        $this->assertSame('2.50', $sale->platformNetRevenue());

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_fee_actual_captured"', $contents);
        $this->assertStringNotContainsString('pagbank.margin.non_positive_net_revenue', $contents);
    }

    #[Test]
    public function logs_a_margin_alert_when_the_real_pagbank_fee_makes_platform_net_revenue_non_positive(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_FEE_2',
                'charges' => [[
                    'id' => 'CHAR_FEE_2',
                    'status' => 'PAID',
                    'fees' => ['value' => 500],
                    'payment_method' => ['type' => 'CREDIT_CARD'],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        // Preço baixo o bastante para a taxa de serviço cair no piso mínimo
        // (R$3) e ficar abaixo do custo PagBank simulado acima (R$5).
        $order = $this->createConfirmedOrder(price: 10, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ])->assertStatus(201);

        $sale = Sale::where('uuid', $order['uuid'])->firstOrFail();

        $this->assertSame('3.00', (string) $sale->platform_fee_total_amount);
        $this->assertSame('5.00', (string) $sale->pagbank_fee_actual);
        $this->assertSame('-2.00', $sale->platformNetRevenue());

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('pagbank.margin.non_positive_net_revenue', $contents);
        $this->assertStringContainsString('"platform_net_revenue":"-2.00"', $contents);
    }

    #[Test]
    public function logs_orders_total_and_payment_approved_metrics_for_a_successful_card_charge(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_METRIC_APPROVED_1',
                'charges' => [[
                    'id' => 'CHAR_METRIC_APPROVED_1',
                    'status' => 'PAID',
                    'payment_method' => ['type' => 'CREDIT_CARD'],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ])->assertStatus(201);

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_orders_total"', $contents);
        $this->assertStringContainsString('"metric":"pagbank_payment_approved"', $contents);
    }

    #[Test]
    public function creates_a_debit_card_order_via_pagbank_using_three_ds_authentication_id(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_DEBIT_1',
                'charges' => [[
                    'id' => 'CHAR_DEBIT_1',
                    'status' => 'PAID',
                    'payment_response' => [
                        'code' => '20000',
                        'message' => 'SUCESSO',
                    ],
                    'payment_method' => [
                        'type' => 'DEBIT_CARD',
                        'installments' => 1,
                    ],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 60, qty: 1);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'debit_card',
            'payer_tax_id' => '12345678909',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_DEBIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '12345678909',
            ],
            'authentication_method' => [
                'type' => 'THREEDS',
                'id' => '3DS_AUTH_123',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.method', 'debit_card');

        Http::assertSent(function ($request) {
            $description = (string) ($request['charges'][0]['description'] ?? '');
            $itemName = (string) ($request['items'][0]['name'] ?? '');

            return $request->url() === 'https://sandbox.api.pagseguro.com/orders'
                && $request['customer']['tax_id'] === '12345678909'
                && $request['customer']['phones'][0]['area'] === '11'
                && $itemName !== ''
                && $request['charges'][0]['payment_method']['type'] === 'DEBIT_CARD'
                && $request['charges'][0]['payment_method']['installments'] === 1
                && $request['charges'][0]['payment_method']['card']['encrypted'] === 'ENCRYPTED_DEBIT_CARD_PAYLOAD'
                && $request['charges'][0]['payment_method']['authentication_method']['type'] === 'THREEDS'
                && $request['charges'][0]['payment_method']['authentication_method']['id'] === '3DS_AUTH_123'
                && str_starts_with($description, 'Compra ')
                && str_contains($description, ' + Test Tenant');
        });

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'confirmed',
            'is_paid' => true,
        ]);
    }

    #[Test]
    public function cancelling_a_paid_order_requests_a_real_refund_in_pagbank(): void
    {
        $orderId = 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328';

        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => $orderId,
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...']],
            ], 201),
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_1',
                    'status' => 'PAID',
                    'amount' => ['value' => 5000, 'currency' => 'BRL'],
                ]],
            ], 200),
            'sandbox.api.pagseguro.com/charges/CHAR_1/cancel' => Http::response([
                'id' => 'CHAR_1',
                'status' => 'CANCELED',
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'cancel');
        $order = $this->createConfirmedOrder(price: 50, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $orderModel = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $payment = $orderModel->payments()->firstOrFail();

        app(SalePaymentService::class)->reconcileWebhookPayment($payment, '50.00');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu após pagar',
        ])->assertStatus(200);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'status' => 'refunded',
            'amount' => '50.00',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/charges/CHAR_1/cancel';
        });

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_refunds"', $contents);
    }

    #[Test]
    public function webhook_with_invalid_signature_is_rejected(): void
    {
        $payload = ['id' => 'ORDE_ABC', 'charges' => [['status' => 'PAID']]];

        $response = $this->postJson('/api/v1/webhooks/payments/pagbank', $payload, [
            'x-authenticity-token' => 'wrong-signature',
        ]);

        $response->assertStatus(401)->assertJsonPath('code', 'WEBHOOK_INVALID_SIGNATURE');
        $this->assertSame(0, WebhookEvent::where('provider', 'pagbank')->count());

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_webhooks_failed"', $contents);
    }

    #[Test]
    public function webhook_with_valid_signature_reconciles_the_payment_after_reconsulting_pagbank(): void
    {
        $orderId = 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328';

        Http::fake([
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_1',
                    'status' => 'PAID',
                    'amount' => ['value' => 8000, 'currency' => 'BRL'],
                ]],
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);
        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => $orderId,
            'method' => 'pix',
            'amount' => '80.00',
            'status' => 'pending',
        ]);

        $rawPayload = json_encode(['id' => $orderId, 'charges' => [['status' => 'PAID']]]);
        $signature = hash('sha256', 'fake-pagbank-token-'.$rawPayload);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/payments/pagbank',
            [],
            [],
            [],
            [
                'HTTP_x-authenticity-token' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'is_paid' => true]);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'pagbank',
            'external_id' => $orderId,
        ]);

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_webhooks_received"', $contents);
    }

    #[Test]
    public function credit_card_charge_with_authorized_status_keeps_sale_unpaid_and_exposes_payment_status(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_AUTH_1',
                'charges' => [[
                    'id' => 'CHAR_AUTH_1',
                    'status' => 'AUTHORIZED',
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                    ],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 90, qty: 1);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'authorized');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider_charge_id' => 'ORDE_AUTH_1',
            'status' => 'authorized',
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'confirmed',
            'is_paid' => false,
        ]);
    }

    #[Test]
    public function credit_card_charge_in_analysis_keeps_sale_unpaid_and_exposes_payment_status(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_ANALYSIS_1',
                'charges' => [[
                    'id' => 'CHAR_ANALYSIS_1',
                    'status' => 'IN_ANALYSIS',
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                    ],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 95, qty: 1);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'in_analysis');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider_charge_id' => 'ORDE_ANALYSIS_1',
            'status' => 'in_analysis',
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'confirmed',
            'is_paid' => false,
        ]);
    }

    #[Test]
    public function storefront_credit_card_charge_declined_by_pagbank_marks_the_sale_as_rejected_immediately(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_DECLINED_1',
                'charges' => [[
                    'id' => 'CHAR_DECLINED_1',
                    'status' => 'DECLINED',
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                    ],
                ]],
            ], 201),
        ]);

        $tenant = $this->createTenantWithStorefrontPlan(true);
        $ticketType = $this->createProduct($tenant->id, ['quantity_available' => 5, 'price' => 120]);

        TenantSettings::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'payment_receiving_method' => 'pagbank_token',
                'pagbank_integration_mode' => 'manual_token',
                'pagbank_environment' => 'sandbox',
                'pagbank_access_token' => 'fake-pagbank-token',
                'storefront_enabled' => true,
            ]
        );

        $checkout = $this->postJson("/api/v1/loja/{$tenant->slug}/checkout", [
            'items' => [
                ['ticket_type_uuid' => $ticketType->uuid, 'quantity' => 1],
            ],
            'client_name' => 'Maria',
            'client_last_name' => 'Silva',
            'client_email' => 'maria.silva@test.com',
            'client_phone' => '11999999999',
            'payment_method' => 'credit_card',
        ])->assertStatus(201)->json('data.sale');

        $sale = Sale::where('uuid', $checkout['uuid'])->firstOrFail();

        $response = $this->postJson("/api/v1/rastreio/{$sale->uuid}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Maria Silva',
            'payer_email' => 'maria.silva@test.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Maria Silva',
                'holder_tax_id' => '12345678909',
                'installments' => 1,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.metadata.provider_status', 'DECLINED');

        $sale->refresh();

        $this->assertSame('rejected', $sale->status);
        $this->assertFalse((bool) $sale->is_paid);

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'provider_charge_id' => 'ORDE_DECLINED_1',
            'status' => 'failed',
        ]);

        $contents = File::get(storage_path('logs/pagbank-transactions.log'));
        $this->assertStringContainsString('"metric":"pagbank_payment_declined"', $contents);
    }

    #[Test]
    #[DataProvider('webhookTerminalStatusesProvider')]
    public function webhook_reconciliation_persists_non_paid_pagbank_statuses_without_leaving_the_sale_without_feedback(
        string $providerStatus,
        string $localStatus
    ): void {
        $orderId = 'ORDE_STATUS_1';

        Http::fake([
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_STATUS_1',
                    'status' => $providerStatus,
                    'amount' => ['value' => 8000, 'currency' => 'BRL'],
                ]],
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 80, qty: 1);
        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => $orderId,
            'method' => 'pix',
            'amount' => '80.00',
            'status' => 'pending',
        ]);

        $rawPayload = json_encode(['id' => $orderId, 'charges' => [['status' => $providerStatus]]]);
        $signature = hash('sha256', 'fake-pagbank-token-'.$rawPayload);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/payments/pagbank',
            [],
            [],
            [],
            [
                'HTTP_x-authenticity-token' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider_charge_id' => $orderId,
            'status' => $localStatus,
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'is_paid' => false,
        ]);
    }

    public static function webhookTerminalStatusesProvider(): array
    {
        return [
            'waiting' => ['WAITING', 'pending'],
            'declined' => ['DECLINED', 'failed'],
            'canceled' => ['CANCELED', 'canceled'],
            'authorized' => ['AUTHORIZED', 'authorized'],
            'in_analysis' => ['IN_ANALYSIS', 'in_analysis'],
        ];
    }

    #[Test]
    public function writes_a_dedicated_pagbank_transaction_log_with_sanitized_data(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_LOG_1',
                'charges' => [[
                    'id' => 'CHAR_LOG_1',
                    'status' => 'PAID',
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                        'card' => [
                            'brand' => 'visa',
                            'first_digits' => '411111',
                            'last_digits' => '1111',
                        ],
                    ],
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 77, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge", [
            'method' => 'credit_card',
            'payer_name' => 'Jose da Silva',
            'payer_email' => 'mreisf.contato@gmail.com',
            'payer_phone' => '11999999999',
            'card' => [
                'encrypted' => 'ENCRYPTED_CREDIT_CARD_PAYLOAD',
                'holder_name' => 'Jose da Silva',
                'holder_tax_id' => '65544332211',
                'installments' => 1,
            ],
        ])->assertStatus(201);

        $logPath = storage_path('logs/pagbank-transactions.log');

        $this->assertFileExists($logPath);

        $contents = File::get($logPath);

        $this->assertStringContainsString('pagbank.create_order.response', $contents);
        $this->assertStringContainsString('"encrypted":"***"', $contents);
        $this->assertStringContainsString('"tax_id":"***"', $contents);
        $this->assertStringContainsString('"email":"***"', $contents);
        $this->assertStringContainsString('"phones":"***"', $contents);
        $this->assertStringNotContainsString('ENCRYPTED_CREDIT_CARD_PAYLOAD', $contents);
        $this->assertStringNotContainsString('mreisf.contato@gmail.com', $contents);
    }

    /**
     * Regressão: `payment_methods` como array (`['CREDIT_CARD']`) faz o
     * PagBank responder payment_methods_is_required, e
     * max_installments_no_interest=1 (default do endpoint) é rejeitado com
     * "should be equal 0 or greater than 1" — confirmado batendo direto na
     * API sandbox real em 2026-08-07. Provider precisa mandar string escalar
     * e normalizar 1 para 0.
     */
    #[Test]
    public function installment_options_request_sends_pagbank_compatible_params(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/charges/fees/calculate*' => Http::response([
                'payment_methods' => [
                    'credit_card' => [
                        'mastercard' => [
                            'installment_plans' => [[
                                'installments' => 1,
                                'installment_value' => 4000,
                                'interest_free' => true,
                                'amount' => ['value' => 4000, 'currency' => 'BRL'],
                            ]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $response = $this->auth()->getJson("/api/v1/sales/{$order['uuid']}/payment-installment-options?credit_card_bin=524008");

        $response->assertStatus(200)->assertJsonPath('data.brand', 'mastercard');

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['payment_methods'] ?? null) === 'CREDIT_CARD'
                && ($query['max_installments_no_interest'] ?? null) === '0'
                && ($query['credit_card_bin'] ?? null) === '524008';
        });
    }

    /**
     * Regressão: BINs de teste fora da base do PagBank (comum em sandbox,
     * confirmado com cartões Visa/Amex de tests/cartoes_teste_pagbank.txt)
     * respondem 400 credit_card_bin_data_not_found. Isso não pode virar erro
     * pro comprador — precisa degradar pra "sem parcelamento disponível".
     */
    #[Test]
    public function installment_options_degrades_gracefully_when_pagbank_does_not_recognize_the_bin(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/charges/fees/calculate*' => Http::response([
                'error_messages' => [[
                    'error' => 'credit_card_bin_data_not_found',
                    'description' => 'credit_card_bin data not found.',
                    'parameter_name' => 'credit_card_bin',
                ]],
            ], 400),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $response = $this->auth()->getJson("/api/v1/sales/{$order['uuid']}/payment-installment-options?credit_card_bin=453962");

        $response->assertStatus(200)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.options', []);
    }

    /**
     * Taxa de serviço PegaTicket (10%/mínimo R$3, 2026-08-12): quando
     * fee_payer='producer' (default do fixture createProduct(), ver
     * CreatesSaleFixtures), o split retém a taxa persistida na Sale
     * (platform_fee_total_amount) ALÉM da taxa fixa já existente da
     * plataforma — sempre a partir do snapshot já gravado, nunca
     * recalculado a partir da config atual.
     */
    #[Test]
    public function split_retains_the_platform_service_fee_when_the_producer_pays_it(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_TENANT_1', 5);

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_PLATFORM',
                'public_key' => 'PLATFORM_PUBLIC_KEY',
            ], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'PLATFORM_3DS_SESSION',
            ], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_FEE_SPLIT_1',
                'charges' => [[
                    'links' => [[
                        'rel' => 'SPLIT',
                        'href' => 'https://sandbox.api.pagseguro.com/splits/SPLI_FEE_SPLIT_1',
                    ]],
                ]],
                'qr_codes' => [[
                    'id' => 'QRCO_FEE_SPLIT_1',
                    'text' => '00020126...feesplit...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        // price=40, qty=2 -> taxa unitaria max(40*10%=4.00,3.00)=4.00,
        // taxa total = 4.00*2 = 8.00 (800 centavos). fee_payer='producer'
        // (default do fixture) -> nao entra no total_amount do comprador,
        // so retida no split.
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        $sale = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $this->assertSame('producer', $sale->platform_fee_payer_snapshot);
        $this->assertSame('8.00', $sale->platform_fee_total_amount);
        $this->assertSame('80.00', $sale->total_amount);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sandbox.api.pagseguro.com/orders') {
                return false;
            }

            $receivers = $request['qr_codes'][0]['splits']['receivers'] ?? [];

            // Taxa fixa da plataforma (R$5,00 = 500) + taxa de serviço
            // PegaTicket retida (R$8,00 = 800) = 1300 centavos pro
            // recebedor da plataforma; o restante (8000-1300=6700) pro
            // organizador.
            return ($receivers[0]['account']['id'] ?? null) === 'ACCO_PLATFORM_1'
                && ($receivers[0]['amount']['value'] ?? null) === 1300
                && ($receivers[1]['account']['id'] ?? null) === 'ACCO_TENANT_1'
                && ($receivers[1]['amount']['value'] ?? null) === 6700;
        });
    }

    /**
     * Roadmap R2.3 (gap 2.2, "religar consumidores"): quando o tenant tem
     * uma TenantPagBankConnection elegível (enabled), o split usa o
     * account_id dela — não o legado tenant_settings.pagbank_receiver_
     * account_id — mesmo que os dois estejam configurados ao mesmo tempo.
     */
    #[Test]
    public function split_configuration_prefers_the_tenant_pagbank_connection_over_the_legacy_tenant_settings(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_LEGACY_TENANT', 5);
        $this->createEnabledPagBankConnection('ACCO_CONNECT_TENANT');

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_PLATFORM',
                'public_key' => 'PLATFORM_PUBLIC_KEY',
            ], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'PLATFORM_3DS_SESSION',
            ], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_CONNECT_SPLIT_1',
                'charges' => [[
                    'links' => [[
                        'rel' => 'SPLIT',
                        'href' => 'https://sandbox.api.pagseguro.com/splits/SPLI_CONNECT_SPLIT_1',
                    ]],
                ]],
                'qr_codes' => [[
                    'id' => 'QRCO_CONNECT_SPLIT_1',
                    'text' => '00020126...connectsplit...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);
        Sale::where('uuid', $order['uuid'])->update(['platform_fee_payer_snapshot' => 'buyer']);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sandbox.api.pagseguro.com/orders') {
                return false;
            }

            $receivers = $request['qr_codes'][0]['splits']['receivers'] ?? [];

            return ($receivers[1]['account']['id'] ?? null) === 'ACCO_CONNECT_TENANT'
                && ($receivers[1]['account']['id'] ?? null) !== 'ACCO_LEGACY_TENANT';
        });
    }

    /**
     * `verified` (caminho Account/Cadastro) conta como elegível no mesmo
     * pé que `enabled` (decisão documentada em
     * TenantReceivingEligibilityService) — o split também deve usar essa
     * conexão como fonte primária.
     */
    #[Test]
    public function split_configuration_uses_a_verified_account_cadastro_connection_as_well(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->createEnabledPagBankConnection('ACCO_VERIFIED_TENANT', TenantPagBankConnection::STATUS_VERIFIED);

        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
            'pagbank_primary_account_id' => 'ACCO_PLATFORM_1',
        ]);

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response([
                'id' => 'PUBKEY_PLATFORM',
                'public_key' => 'PLATFORM_PUBLIC_KEY',
            ], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response([
                'session' => 'PLATFORM_3DS_SESSION',
            ], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_VERIFIED_SPLIT_1',
                'charges' => [[
                    'links' => [[
                        'rel' => 'SPLIT',
                        'href' => 'https://sandbox.api.pagseguro.com/splits/SPLI_VERIFIED_SPLIT_1',
                    ]],
                ]],
                'qr_codes' => [[
                    'id' => 'QRCO_VERIFIED_SPLIT_1',
                    'text' => '00020126...verifiedsplit...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);
        Sale::where('uuid', $order['uuid'])->update(['platform_fee_payer_snapshot' => 'buyer']);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://sandbox.api.pagseguro.com/orders') {
                return false;
            }

            $receivers = $request['qr_codes'][0]['splits']['receivers'] ?? [];

            return ($receivers[0]['account']['id'] ?? null) === 'ACCO_VERIFIED_TENANT';
        });
    }

    /**
     * Roadmap R2.6 (gap 9.5.8): snapshot por venda do receptor efetivamente
     * usado no split — `resolveSplitSettings()` é reconsultado a cada
     * tentativa de cobrança a partir da config ATUAL, então sem esse
     * snapshot uma reconciliação futura não saberia reconstruir quem
     * recebeu o split desta venda específica se a config do tenant mudar
     * depois.
     */
    #[Test]
    public function receiver_snapshot_records_the_tenant_pagbank_connection_as_source_when_eligible(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_LEGACY_TENANT', 5);
        $this->createEnabledPagBankConnection('ACCO_CONNECT_TENANT');

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response(['id' => 'PUBKEY_1', 'public_key' => 'PK'], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response(['session' => 'SESSION'], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_SNAPSHOT_CONNECT_1',
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...6304ABCD']],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);
        Sale::where('uuid', $order['uuid'])->update(['platform_fee_payer_snapshot' => 'buyer']);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'uuid' => $order['uuid'],
            'tenant_receiver_provider' => 'pagbank',
            'tenant_receiver_account_id_snapshot' => 'ACCO_CONNECT_TENANT',
            'tenant_receiver_source_snapshot' => 'tenant_pagbank_connection',
        ]);
    }

    #[Test]
    public function receiver_snapshot_records_the_legacy_tenant_settings_as_source_when_no_connection_exists(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_LEGACY_TENANT', 5);

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response(['id' => 'PUBKEY_1', 'public_key' => 'PK'], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response(['session' => 'SESSION'], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_SNAPSHOT_LEGACY_1',
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...6304ABCD']],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);
        Sale::where('uuid', $order['uuid'])->update(['platform_fee_payer_snapshot' => 'buyer']);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'uuid' => $order['uuid'],
            'tenant_receiver_provider' => 'pagbank',
            'tenant_receiver_account_id_snapshot' => 'ACCO_LEGACY_TENANT',
            'tenant_receiver_source_snapshot' => 'legacy_tenant_settings',
        ]);
    }

    #[Test]
    public function receiver_snapshot_records_none_when_there_is_no_split_configured(): void
    {
        // Sem PlatformFinanceSettings/tenant_settings pagbank configurados
        // -> resolveSplitSettings() retorna null (cai no stub sem
        // credenciais, mesmo assim buildSplitPayload roda antes do envio).
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_SNAPSHOT_NONE_1',
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...6304ABCD']],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $this->assertDatabaseHas('sales', [
            'uuid' => $order['uuid'],
            'tenant_receiver_provider' => null,
            'tenant_receiver_account_id_snapshot' => null,
            'tenant_receiver_source_snapshot' => 'none',
        ]);
    }

    #[Test]
    public function receiver_snapshot_is_overwritten_by_a_new_attempt_while_the_sale_is_not_paid_but_frozen_once_paid(): void
    {
        $this->configureTenantPagBankDirect('tenant-token-123', 'production');
        $this->configureSplitAccounts('ACCO_PLATFORM_1', 'ACCO_LEGACY_TENANT', 5);
        $this->createEnabledPagBankConnection('ACCO_CONNECT_TENANT');

        Http::fake([
            'sandbox.api.pagseguro.com/public-keys' => Http::response(['id' => 'PUBKEY_1', 'public_key' => 'PK'], 201),
            'sandbox.sdk.pagseguro.com/checkout-sdk/sessions' => Http::response(['session' => 'SESSION'], 200),
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_SNAPSHOT_RETRY_1',
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...6304ABCD']],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 1);
        $sale = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $sale->update(['platform_fee_payer_snapshot' => 'buyer']);

        // Primeira tentativa: resolve via TenantPagBankConnection.
        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);
        $sale->refresh();
        $this->assertSame('ACCO_CONNECT_TENANT', $sale->tenant_receiver_account_id_snapshot);
        $this->assertSame('tenant_pagbank_connection', $sale->tenant_receiver_source_snapshot);

        // Simula tentativa anterior falha (ex.: cartão recusado) para não
        // esbarrar no guard de "cobrança pendente já ativa" do
        // SalePaymentService — cenário realista de nova tentativa.
        Payment::where('payable_id', $sale->id)->where('payable_type', Sale::class)->update(['status' => 'failed']);

        // Config do tenant muda (reconecta outra conta) ANTES de a venda
        // ser paga -> nova tentativa deve sobrescrever o snapshot.
        TenantPagBankConnection::where('tenant_id', $this->tenant->id)
            ->update(['account_id' => 'ACCO_CONNECT_TENANT_NEW']);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);
        $sale->refresh();
        $this->assertSame('ACCO_CONNECT_TENANT_NEW', $sale->tenant_receiver_account_id_snapshot);

        // Venda agora paga: snapshot deve congelar, mesmo que a config
        // mude de novo e uma nova tentativa de cobrança seja feita.
        Payment::where('payable_id', $sale->id)->where('payable_type', Sale::class)->update(['status' => 'failed']);
        $sale->update(['is_paid' => true, 'paid_at' => now()]);
        TenantPagBankConnection::where('tenant_id', $this->tenant->id)
            ->update(['account_id' => 'ACCO_CONNECT_TENANT_AFTER_PAID']);

        // Venda já paga -> SalePaymentService rejeita nova cobrança antes
        // de chegar no provider (already_paid); confirma que o snapshot
        // permanece o da última tentativa pré-pagamento.
        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(422);
        $sale->refresh();
        $this->assertSame('ACCO_CONNECT_TENANT_NEW', $sale->tenant_receiver_account_id_snapshot);
        $this->assertSame('tenant_pagbank_connection', $sale->tenant_receiver_source_snapshot);
    }
}
