<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * Histórico de vouchers do portal do cliente final
 * (`GET /portal/coupon-redemptions`). Guard de posse: só resgates do
 * cliente autenticado.
 */
class PortalAddressAndVouchersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function createTenant(?string $name = null): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name ?? 'Tenant ' . Str::random(6),
            'slug' => 'tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@enderecos.test'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function confirmLink(FinalCustomer $customer, Tenant $tenant, $client): void
    {
        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'confirmed_at' => now(),
        ]);
    }

    #[Test]
    public function voucher_history_only_returns_the_authenticated_customers_redemptions(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();
        [$otherCustomer] = $this->authenticatedCustomer('outro@enderecos.test');

        $tenant = $this->createTenant('Loja do Cupom');
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => false,
            'is_delivered' => false,
        ]);

        $coupon = Coupon::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'code' => 'PROMO10',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
        ]);

        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $customer->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        // Voucher de OUTRO cliente — nunca deve aparecer.
        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $otherCustomer->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/coupon-redemptions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coupon_code', 'PROMO10')
            ->assertJsonPath('data.0.tenant_name', 'Loja do Cupom')
            ->assertJsonPath('data.0.order_uuid', $order->uuid);
    }

    #[Test]
    public function voucher_history_requires_authentication(): void
    {
        $this->getJson('/api/v1/portal/coupon-redemptions')->assertStatus(401);
    }
}
