<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Support\PagBankTestCards;
use Tests\TestCase;

/**
 * Cobrança Pix do PRÓPRIO pedido via Portal (roadmap Fase B, item 1 —
 * checkout Pix na loja pública): POST /portal/sales/{uuid}/payment-charge.
 * Usa ManualPaymentProvider (config default), a integração real com
 * MercadoPagoPaymentProvider já é coberta por MercadoPagoSalePaymentTest.
 */
class PortalSalePaymentChargeTest extends TestCase
{
    use PagBankTestCards;
    use RefreshDatabase;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.sale_provider', 'manual');
    }

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function createOrder(Tenant $tenant, FinalCustomer $owner, array $overrides = []): Sale
    {

        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $owner->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_completed' => false,
        ], $overrides));
    }

    private function linkCustomerToOrder(string $token, string $saleUuid): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $saleUuid])
            ->assertStatus(200);
    }

    #[Test]
    public function creates_a_pix_charge_for_the_customers_own_order(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge');

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'pix');

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function returns_404_for_order_belonging_to_another_customer(): void
    {
        [$customerA, $tokenA] = $this->authenticatedCustomer('a@test.com');
        [, $tokenB] = $this->authenticatedCustomer('b@test.com');

        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customerA);

        $this->linkCustomerToOrder($tokenA, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge')
            ->assertStatus(404);

        $this->assertDatabaseMissing('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
        ]);
    }

    #[Test]
    public function rejects_when_order_is_already_paid(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer, ['is_paid' => true, 'paid_at' => now()]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');
    }

    #[Test]
    public function does_not_create_a_second_active_pix_charge(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('cliente@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer);

        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge')
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $this->assertSame(1, Payment::where('payable_type', Sale::class)->where('payable_id', $order->id)->count());
    }

    #[Test]
    public function creates_a_credit_card_charge_for_the_customers_own_order_via_pagbank(): void
    {
        Config::set('services.payments.sale_provider', 'pagbank');
        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-pagbank-token');

        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_PORTAL_CARD_1',
                'charges' => [[
                    'id' => 'CHAR_PORTAL_CARD_1',
                    'status' => 'PAID',
                    'payment_method' => [
                        'type' => 'CREDIT_CARD',
                        'installments' => 1,
                        'card' => [
                            'brand' => 'visa',
                            'last_digits' => '2097',
                        ],
                    ],
                ]],
            ], 201),
        ]);

        $card = self::pagBankSandboxCards()[0];

        [$customer, $token] = $this->authenticatedCustomer('mreisf.contato@gmail.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant, $customer, ['total_amount' => 120]);

        $this->linkCustomerToOrder($token, $order->uuid);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/sales/' . $order->uuid . '/payment-charge', [
                'method' => 'credit_card',
                'payer_name' => 'Maria Reis',
                'payer_email' => 'mreisf.contato@gmail.com',
                'payer_phone' => '11999999999',
                'card' => [
                    'encrypted' => $card['encrypted'],
                    'holder_name' => 'Maria Reis',
                    'holder_tax_id' => '12345678909',
                    'installments' => 1,
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.method', 'credit_card');

        Http::assertSent(function ($request) use ($card) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/orders'
                && $request['customer']['email'] === 'mreisf.contato@gmail.com'
                && $request['charges'][0]['payment_method']['type'] === 'CREDIT_CARD'
                && $request['charges'][0]['payment_method']['card']['encrypted'] === $card['encrypted'];
        });
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/v1/portal/sales/' . Str::uuid() . '/payment-charge')
            ->assertStatus(401);
    }
}
