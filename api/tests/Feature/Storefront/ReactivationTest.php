<?php

namespace Tests\Feature\Storefront;

use App\Models\Client\Client;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\ReactivationRule;
use App\Models\Stock\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Régua de reativação de cliente (roadmap A5, item 18) — CRUD singleton
 * da regra (GET/PUT /reactivation-rule) + comando agendado
 * reactivation:process (gera cupom + push para clientes sem pedido há N
 * dias).
 */
class ReactivationTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('reactivation-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createLocationHere(): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
        ]);
    }

    private function createOrderForClient(Client $client, StockLocation $location, array $overrides = []): Order
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $order = Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));

        // 'created_at' não é mass-assignable (não está no $fillable de
        // Order) — forceFill()->save() pra simular pedido antigo.
        if ($createdAt) {
            $order->forceFill(['created_at' => $createdAt])->save();
        }

        return $order;
    }

    private function linkFinalCustomer(Client $client, string $email): FinalCustomer
    {
        $customer = FinalCustomer::create(['email' => $email]);

        FinalCustomerTenantLink::create([
            'final_customer_id' => $customer->id,
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'confirmed_at' => now(),
        ]);

        return $customer;
    }

    #[Test]
    public function get_reactivation_rule_returns_defaults_on_first_read(): void
    {
        $this->grantPermission('reactivation', 'read');

        $response = $this->auth()->getJson('/api/v1/reactivation-rule');

        $response->assertStatus(200)
            ->assertJsonPath('data.days_without_order', 30)
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function put_reactivation_rule_updates_and_persists(): void
    {
        $this->grantPermission('reactivation', 'update');

        $response = $this->auth()->putJson('/api/v1/reactivation-rule', [
            'days_without_order' => 45,
            'coupon_type' => 'fixed',
            'coupon_value' => 15,
            'coupon_validity_days' => 10,
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.days_without_order', 45)
            ->assertJsonPath('data.coupon_type', 'fixed')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('reactivation_rules', [
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 45,
            'coupon_type' => 'fixed',
        ]);
    }

    #[Test]
    public function reactivation_rule_endpoints_require_permission(): void
    {
        $this->auth()->getJson('/api/v1/reactivation-rule')->assertStatus(403);
        $this->auth()->putJson('/api/v1/reactivation-rule', [
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 10,
            'coupon_validity_days' => 7,
        ])->assertStatus(403);
    }

    #[Test]
    public function process_generates_coupon_and_dispatch_for_eligible_client_with_confirmed_link(): void
    {
        ReactivationRule::create([
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 15,
            'coupon_validity_days' => 7,
            'is_active' => true,
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocationHere();
        $this->createOrderForClient($client, $location, ['created_at' => now()->subDays(40)]);
        $this->linkFinalCustomer($client, 'inactive-client@test.com');

        Artisan::call('reactivation:process');

        $this->assertDatabaseHas('reactivation_dispatches', [
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
        ]);

        $coupon = Coupon::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($coupon);
        $this->assertEquals('percentage', $coupon->type);
        $this->assertEquals(1, $coupon->max_uses_total);
        $this->assertEquals(1, $coupon->max_uses_per_customer);
        $this->assertTrue(str_starts_with($coupon->code, 'REATIV-'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'reactivation_dispatched']);
    }

    #[Test]
    public function process_skips_client_with_recent_order(): void
    {
        ReactivationRule::create([
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 15,
            'coupon_validity_days' => 7,
            'is_active' => true,
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocationHere();
        $this->createOrderForClient($client, $location, ['created_at' => now()->subDays(5)]);
        $this->linkFinalCustomer($client, 'recent-client@test.com');

        Artisan::call('reactivation:process');

        $this->assertDatabaseCount('reactivation_dispatches', 0);
    }

    #[Test]
    public function process_skips_client_without_confirmed_final_customer_link(): void
    {
        ReactivationRule::create([
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 15,
            'coupon_validity_days' => 7,
            'is_active' => true,
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocationHere();
        $this->createOrderForClient($client, $location, ['created_at' => now()->subDays(40)]);
        // Sem FinalCustomerTenantLink confirmado — não há como notificar.

        Artisan::call('reactivation:process');

        $this->assertDatabaseCount('reactivation_dispatches', 0);
    }

    #[Test]
    public function process_respects_cooldown_and_does_not_duplicate_coupon_before_previous_one_expires(): void
    {
        ReactivationRule::create([
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 15,
            'coupon_validity_days' => 7,
            'is_active' => true,
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocationHere();
        $this->createOrderForClient($client, $location, ['created_at' => now()->subDays(40)]);
        $this->linkFinalCustomer($client, 'cooldown-client@test.com');

        Artisan::call('reactivation:process');
        $this->assertDatabaseCount('reactivation_dispatches', 1);

        Artisan::call('reactivation:process');
        $this->assertDatabaseCount('reactivation_dispatches', 1);
    }

    #[Test]
    public function process_ignores_inactive_rules(): void
    {
        ReactivationRule::create([
            'tenant_id' => $this->tenant->id,
            'days_without_order' => 30,
            'coupon_type' => 'percentage',
            'coupon_value' => 15,
            'coupon_validity_days' => 7,
            'is_active' => false,
        ]);

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocationHere();
        $this->createOrderForClient($client, $location, ['created_at' => now()->subDays(40)]);
        $this->linkFinalCustomer($client, 'inactive-rule-client@test.com');

        Artisan::call('reactivation:process');

        $this->assertDatabaseCount('reactivation_dispatches', 0);
    }
}
