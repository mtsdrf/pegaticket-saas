<?php

namespace Tests\Feature\Storefront;

use App\Exceptions\CouponUsageLimitReachedException;
use App\Exceptions\InvalidCouponException;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Models\Tenant\Tenant;
use App\Services\Storefront\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * GET/POST/PUT/DELETE /coupons (Delivery Fase 3) — CRUD completo, diferente
 * de catálogo promocional. validateForCheckout() é testado aqui
 * diretamente via o Service (guard 4 do checkout/consumo de
 * CouponRedemption é a PRÓXIMA fatia do roadmap, ainda não integrado em
 * StorefrontCheckoutService::checkout()).
 */
class CouponTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('coupon-user@test.com');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    #[Test]
    public function store_creates_a_percentage_coupon(): void
    {
        $this->grantPermission('storefront', 'update');

        $response = $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'PROMO10',
            'type' => 'percentage',
            'value' => 10.5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'PROMO10')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.value', 10.5)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('coupons', [
            'tenant_id' => $this->tenant->id,
            'code' => 'PROMO10',
            'type' => 'percentage',
        ]);
    }

    /**
     * Achado de code review: `value` era sempre `nullable`, mesmo pra
     * percentage/fixed — sem essa trava, um cupom sem valor passaria em
     * todos os guards do checkout e sempre descontaria 0, consumindo o
     * limite de uso do cliente sem nunca dar desconto nenhum.
     */
    #[Test]
    public function store_requires_value_for_percentage_and_fixed_types(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/coupons', ['code' => 'SEMVALOR', 'type' => 'percentage'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');

        $this->auth()->postJson('/api/v1/coupons', ['code' => 'SEMVALOR2', 'type' => 'fixed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    #[Test]
    public function store_allows_free_shipping_without_value(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/coupons', ['code' => 'FRETEGRATIS', 'type' => 'free_shipping'])
            ->assertStatus(201)
            ->assertJsonPath('data.value', null);
    }

    #[Test]
    public function store_rejects_percentage_value_above_100(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/coupons', ['code' => 'EXAGERADO', 'type' => 'percentage', 'value' => 150])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');
    }

    #[Test]
    public function store_rejects_duplicate_code_for_the_same_tenant(): void
    {
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'DUP10',
            'type' => 'fixed',
            'value' => 5,
        ])->assertStatus(201);

        $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'DUP10',
            'type' => 'fixed',
            'value' => 8,
        ])->assertStatus(422);
    }

    #[Test]
    public function update_changes_coupon_fields(): void
    {
        $this->grantPermission('storefront', 'update');

        $created = $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'UPDATEME',
            'type' => 'fixed',
            'value' => 5,
        ])->assertStatus(201);

        $uuid = $created->json('data.uuid');

        $this->auth()->putJson('/api/v1/coupons/' . $uuid, [
            'code' => 'UPDATEME',
            'type' => 'fixed',
            'value' => 20.5,
            'is_active' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.value', 20.5)
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function index_lists_only_coupons_of_current_tenant(): void
    {
        $this->grantPermission('storefront', 'read');
        $this->grantPermission('storefront', 'update');

        $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'MINE',
            'type' => 'free_shipping',
        ])->assertStatus(201);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        Coupon::create([
            'tenant_id' => $otherTenant->id,
            'code' => 'OTHER',
            'type' => 'fixed',
            'value' => 1,
        ]);

        $response = $this->auth()->getJson('/api/v1/coupons')->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('MINE', $data[0]['code']);
    }

    #[Test]
    public function destroy_removes_the_coupon(): void
    {
        $this->grantPermission('storefront', 'update');

        $created = $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'REMOVEME',
            'type' => 'fixed',
            'value' => 3,
        ])->assertStatus(201);

        $uuid = $created->json('data.uuid');

        $this->auth()->deleteJson('/api/v1/coupons/' . $uuid)->assertStatus(204);

        $this->assertSoftDeleted('coupons', [
            'tenant_id' => $this->tenant->id,
            'code' => 'REMOVEME',
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_manage_coupons(): void
    {
        $this->auth()->getJson('/api/v1/coupons')->assertStatus(403);
        $this->auth()->postJson('/api/v1/coupons', [
            'code' => 'NOPE',
            'type' => 'fixed',
            'value' => 1,
        ])->assertStatus(403);
    }

    private function customer(string $email = 'cliente@coupon.test'): FinalCustomer
    {
        return FinalCustomer::create(['email' => $email]);
    }

    #[Test]
    public function validate_for_checkout_blocks_inactive_coupon(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'INACTIVE',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => false,
        ]);

        $customer = $this->customer();

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 10000);
    }

    #[Test]
    public function validate_for_checkout_blocks_nonexistent_code(): void
    {
        $customer = $this->customer();

        $this->expectException(InvalidCouponException::class);
        app(CouponService::class)->validateForCheckout($this->tenant->id, 'DOESNOTEXIST', $customer->id, 10000);
    }

    #[Test]
    public function validate_for_checkout_blocks_expired_coupon(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'EXPIRED',
            'type' => 'fixed',
            'value' => 5,
            'expires_at' => now()->subDay(),
        ]);

        $customer = $this->customer();

        try {
            app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 10000);
            $this->fail('Expected InvalidCouponException');
        } catch (InvalidCouponException $e) {
            $this->assertSame(__('messages.coupon.expired'), $e->getMessage());
        }
    }

    #[Test]
    public function validate_for_checkout_blocks_coupon_not_yet_available(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'FUTURE',
            'type' => 'fixed',
            'value' => 5,
            'starts_at' => now()->addDay(),
        ]);

        $customer = $this->customer();

        try {
            app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 10000);
            $this->fail('Expected InvalidCouponException');
        } catch (InvalidCouponException $e) {
            $this->assertSame(__('messages.coupon.not_yet_available'), $e->getMessage());
        }
    }

    #[Test]
    public function validate_for_checkout_blocks_below_coupon_minimum(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MIN50',
            'type' => 'fixed',
            'value' => 5,
            'minimum_order_value' => 50,
        ]);

        $customer = $this->customer();

        $this->expectException(InvalidCouponException::class);
        // R$30,00 em centavos, abaixo do mínimo de R$50,00.
        app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 3000);
    }

    #[Test]
    public function validate_for_checkout_passes_when_above_coupon_minimum(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MIN50OK',
            'type' => 'fixed',
            'value' => 5,
            'minimum_order_value' => 50,
        ]);

        $customer = $this->customer();

        $result = app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 5000);
        $this->assertSame($coupon->id, $result->id);
    }

    #[Test]
    public function validate_for_checkout_blocks_when_total_usage_limit_reached(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'LIMIT1',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_total' => 1,
        ]);

        $this->grantPermission('storefront', 'update');
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);
        $order = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_completed' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
        ]);

        $customer = $this->customer();

        CouponRedemption::create([
            'tenant_id' => $this->tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $customer->id,
            'sale_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        // Cliente diferente também é bloqueado — o limite já esgotou é o
        // limite TOTAL, não por cliente.
        $anotherCustomer = $this->customer('outro@coupon.test');

        $this->expectException(CouponUsageLimitReachedException::class);
        app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $anotherCustomer->id, 10000);
    }

    #[Test]
    public function validate_for_checkout_blocks_when_per_customer_usage_limit_reached(): void
    {
        $coupon = Coupon::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PERCUSTOMER1',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_per_customer' => 1,
        ]);

        $client = $this->createClient($this->tenant->id);
        $order = Sale::create([
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_completed' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
        ]);

        $customer = $this->customer();

        CouponRedemption::create([
            'tenant_id' => $this->tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $customer->id,
            'sale_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        $this->expectException(CouponUsageLimitReachedException::class);
        app(CouponService::class)->validateForCheckout($this->tenant->id, $coupon->code, $customer->id, 10000);
    }
}
