<?php

namespace Tests\Feature\Security;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Segurança dedicada de checkout (roadmap R4, gaps 2.5/2.6): anti-bot
 * (App\Services\Security\AntiBotGuardService) nas 2 rotas públicas de
 * payment-charge (rastreio, portal) e limite de tentativas por venda
 * (App\Services\Security\PaymentChargeAttemptLimiter) nas 3 rotas
 * (rastreio, portal, staff).
 */
class PaymentChargeSecurityTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.sale_provider', 'manual');
    }

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.Str::random(6),
            'slug' => 'tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function createOrder(int $tenantId, int $customerId, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'final_customer_id' => $customerId,
            'origin' => 'storefront',
            'status' => 'pending_approval',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_completed' => false,
        ], $overrides));
    }

    private function authenticatedCustomer(string $email): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function linkCustomerToOrder(string $token, string $saleUuid): void
    {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $saleUuid])
            ->assertStatus(200);
    }

    // --- Anti-bot: rastreio público ---

    #[Test]
    public function rejects_public_tracking_payment_charge_when_honeypot_is_filled(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'rastreio-honeypot@test.com']);
        $order = $this->createOrder($tenant->id, $customer->id);

        $this->postJson('/api/v1/rastreio/'.$order->uuid.'/payment-charge', [
            'website' => 'https://bot-spam.example',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
        ]);
    }

    #[Test]
    public function rejects_public_tracking_payment_charge_when_submitted_too_fast(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'rastreio-timing@test.com']);
        $order = $this->createOrder($tenant->id, $customer->id);

        $this->postJson('/api/v1/rastreio/'.$order->uuid.'/payment-charge', [
            'form_rendered_at' => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    #[Test]
    public function allows_public_tracking_payment_charge_with_empty_honeypot_and_enough_fill_time(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'rastreio-legit@test.com']);
        $order = $this->createOrder($tenant->id, $customer->id);

        $this->postJson('/api/v1/rastreio/'.$order->uuid.'/payment-charge', [
            'website' => '',
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
        ])->assertStatus(201);
    }

    // --- Anti-bot: portal do cliente ---

    #[Test]
    public function rejects_portal_payment_charge_when_honeypot_is_filled(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('portal-honeypot@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant->id, $customer->id);
        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/sales/'.$order->uuid.'/payment-charge', [
                'website' => 'https://bot-spam.example',
            ])->assertStatus(422);
    }

    #[Test]
    public function rejects_portal_payment_charge_when_submitted_too_fast(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('portal-timing@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant->id, $customer->id);
        $this->linkCustomerToOrder($token, $order->uuid);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/sales/'.$order->uuid.'/payment-charge', [
                'form_rendered_at' => now()->toIso8601String(),
            ])->assertStatus(422);
    }

    // --- Staff (autenticado): anti-bot NÃO se aplica ---

    #[Test]
    public function staff_payment_charge_does_not_require_honeypot_or_turnstile(): void
    {
        Config::set('services.turnstile.secret_key', 'test-secret');

        $this->setUpTenantScopedUser('staff-antibot@test.com');
        $this->grantPermission('sales', 'update');

        $customer = FinalCustomer::create(['email' => 'staff-antibot-customer@test.com']);
        $order = $this->createOrder($this->tenant->id, $customer->id);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/sales/'.$order->uuid.'/payment-charge')
            ->assertStatus(201);
    }

    // --- Limite de tentativas por venda (anti card-testing) ---

    #[Test]
    public function blocks_public_tracking_payment_charge_after_too_many_failed_attempts_on_the_same_sale(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'rastreio-attempts@test.com']);
        $order = $this->createOrder($tenant->id, $customer->id, ['cancelled_at' => now()]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/rastreio/'.$order->uuid.'/payment-charge', [
                'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/rastreio/'.$order->uuid.'/payment-charge', [
            'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
        ])->assertStatus(429);
    }

    #[Test]
    public function blocks_portal_payment_charge_after_too_many_failed_attempts_on_the_same_sale(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('portal-attempts@test.com');
        $tenant = $this->createTenant();
        $order = $this->createOrder($tenant->id, $customer->id, ['cancelled_at' => now()]);
        $this->linkCustomerToOrder($token, $order->uuid);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/portal/sales/'.$order->uuid.'/payment-charge', [
                    'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
                ])->assertStatus(422);
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/portal/sales/'.$order->uuid.'/payment-charge', [
                'form_rendered_at' => now()->subSeconds(10)->toIso8601String(),
            ])->assertStatus(429);
    }

    #[Test]
    public function blocks_staff_payment_charge_after_too_many_failed_attempts_on_the_same_sale(): void
    {
        $this->setUpTenantScopedUser('staff-attempts@test.com');
        $this->grantPermission('sales', 'update');

        $customer = FinalCustomer::create(['email' => 'staff-attempts-customer@test.com']);
        $order = $this->createOrder($this->tenant->id, $customer->id, ['cancelled_at' => now()]);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$this->token)
                ->postJson('/api/v1/sales/'.$order->uuid.'/payment-charge')
                ->assertStatus(422);
        }

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/sales/'.$order->uuid.'/payment-charge')
            ->assertStatus(429);
    }
}
