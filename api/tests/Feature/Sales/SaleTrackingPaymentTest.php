<?php

namespace Tests\Feature\Sales;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaleTrackingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.sale_provider', 'manual');
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

    private function createOrder(Tenant $tenant, FinalCustomer $customer, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'origin' => 'storefront',
            'status' => 'pending_approval',
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_completed' => false,
        ], $overrides));
    }

    #[Test]
    public function public_tracking_payment_charge_creates_a_pix_charge_without_authentication(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'cliente-publico@test.com']);
        $order = $this->createOrder($tenant, $customer);

        $this->postJson('/api/v1/rastreio/' . $order->uuid . '/payment-charge')
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'pix');

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function public_tracking_payment_checkout_config_is_available_without_authentication(): void
    {
        $tenant = $this->createTenant();
        $customer = FinalCustomer::create(['email' => 'cliente-config@test.com']);
        $order = $this->createOrder($tenant, $customer);

        $this->getJson('/api/v1/rastreio/' . $order->uuid . '/payment-checkout-config')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['provider', 'available']]);
    }
}
