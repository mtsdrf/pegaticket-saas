<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Services\Auth\CustomerJWTService;
use App\Services\Order\OrderService;
use App\Services\Tenant\TenantExecutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Guard 4 do checkout público (Delivery Fase 3) — integração de cupom:
 * cálculo de discount_amount/coupon_id, criação/remoção de
 * CouponRedemption e a prévia pública (POST /loja/{slug}/cupons/validar).
 * Ver App\Services\Storefront\StorefrontCheckoutService::checkout().
 */
class StorefrontCheckoutCouponTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function authenticatedCustomer(string $email = 'cliente@coupon-checkout.test'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    private function createAddressTrio(): array
    {
        $estado = Estado::create(['name' => 'Estado ' . Str::random(6), 'uf' => $this->nextUf()]);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'name' => 'Cidade ' . Str::random(6)]);
        $bairro = Bairro::create(['cidade_id' => $cidade->id, 'name' => 'Bairro ' . Str::random(6)]);

        return [$estado, $cidade, $bairro];
    }

    private function checkoutPayload(string $productUuid, array $address, array $overrides = []): array
    {
        [$estado, $cidade, $bairro] = $address;

        return array_merge([
            'items' => [
                ['ticket_type_uuid' => $productUuid, 'quantity' => 2],
            ],
            'client_name' => 'Cliente Loja',
            'client_last_name' => 'Sobrenome',
            'client_phone' => '11999998888',
            'notes' => null,
            'estado_uuid' => $estado->uuid,
            'cidade_uuid' => $cidade->uuid,
            'bairro_uuid' => $bairro->uuid,
            'logradouro' => 'Rua da Loja, 100',
            'numero' => '100',
            'complemento' => null,
            'cep' => '01000-000',
        ], $overrides);
    }

    private function setupStore(): array
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2], 8.0);

        return [$tenant, $product, $address];
    }

    #[Test]
    public function checkout_applies_percentage_coupon_discount(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PROMO10',
            'type' => 'percentage',
            'value' => 10,
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'PROMO10'])
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Subtotal 2x R$10 = R$20; 10% = R$2,00 de desconto; +R$8,00 de
        // entrega = R$26,00.
        $this->assertEquals(2.0, (float) $order->discount_amount);
        $this->assertEquals(26.0, (float) $order->total_amount);
        $this->assertNotNull($order->coupon_id);

        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $order->coupon_id,
            'order_id' => $order->id,
        ]);
    }

    #[Test]
    public function checkout_applies_fixed_coupon_discount_capped_at_subtotal(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'FIXED50',
            'type' => 'fixed',
            'value' => 50,
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'FIXED50'])
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Cupom de R$50 nunca desconta mais que o subtotal (R$20) — total
        // fica só com a entrega (R$8,00).
        $this->assertEquals(20.0, (float) $order->discount_amount);
        $this->assertEquals(8.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_applies_free_shipping_coupon_zeroing_delivery_cost(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'FREESHIP',
            'type' => 'free_shipping',
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'FREESHIP'])
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Desconto = R$8,00 (igual à entrega); total = subtotal (R$20) +
        // entrega (R$8) - desconto (R$8) = R$20,00. delivery_fee continua
        // persistido separadamente para o breakdown.
        $this->assertEquals(8.0, (float) $order->discount_amount);
        $this->assertEquals(8.0, (float) $order->delivery_fee);
        $this->assertEquals(20.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_without_coupon_code_keeps_discount_zero_and_coupon_null(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        $this->assertEquals(0.0, (float) $order->discount_amount);
        $this->assertNull($order->coupon_id);
    }

    #[Test]
    public function checkout_blocks_with_expired_coupon(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'EXPIRED',
            'type' => 'fixed',
            'value' => 5,
            'expires_at' => now()->subDay(),
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'EXPIRED'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_blocks_with_inactive_coupon(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'INACTIVE',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => false,
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'INACTIVE'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_blocks_with_coupon_below_its_own_minimum(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'MIN100',
            'type' => 'fixed',
            'value' => 5,
            'minimum_order_value' => 100,
        ]);

        [, $token] = $this->authenticatedCustomer();

        // Subtotal R$20, abaixo do mínimo do cupom (R$100).
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'MIN100'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_blocks_when_total_usage_limit_reached(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        $coupon = Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'LIMIT1',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_total' => 1,
        ]);

        $otherClient = $this->createClient($tenant->id);
        $otherOrder = Order::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $otherClient->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        $otherCustomer = FinalCustomer::create(['email' => 'outro@coupon-checkout.test']);
        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $otherCustomer->id,
            'order_id' => $otherOrder->id,
            'redeemed_at' => now(),
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'LIMIT1'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'COUPON_USAGE_LIMIT_REACHED');
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function checkout_blocks_when_per_customer_usage_limit_reached(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        $coupon = Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PERCUSTOMER1',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_per_customer' => 1,
        ]);

        [$customer, $token] = $this->authenticatedCustomer();

        // Primeira compra: aplica o cupom com sucesso.
        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'PERCUSTOMER1'])
            );

        $first->assertStatus(201);

        // Segunda compra, mesmo cliente: limite por cliente já esgotado.
        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'PERCUSTOMER1'])
            );

        $second->assertStatus(422)->assertJsonPath('code', 'COUPON_USAGE_LIMIT_REACHED');
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function reject_removes_coupon_redemption_freeing_the_usage_limit(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'REUSABLE',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_per_customer' => 1,
        ]);

        [, $token] = $this->authenticatedCustomer();

        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'REUSABLE'])
            );

        $first->assertStatus(201);

        $order = Order::where('uuid', $first->json('data.order.uuid'))->firstOrFail();
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id]);

        app(TenantExecutionContext::class)->run(
            $tenant,
            fn () => app(OrderService::class)->reject($order, 'Fora de estoque')
        );

        $this->assertDatabaseMissing('coupon_redemptions', ['order_id' => $order->id]);

        // Cliente pode reusar o cupom depois — limite por cliente não
        // conta mais o pedido recusado.
        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'REUSABLE'])
            );

        $second->assertStatus(201);
    }

    #[Test]
    public function staff_orders_keep_discount_amount_zero_and_coupon_id_null(): void
    {
        $this->setUpTenantScopedUser('staff@coupon-checkout.test');
        $this->grantPermission('orders', 'create');

        $tenant = $this->tenant;
        $client = $this->createClient($tenant->id);
        $location = $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        $this->stockEntry($tenant->id, $product, $location, 10);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders', [
                'final_customer_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'is_installment' => false,
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();

        $this->assertEquals(0.0, (float) $order->discount_amount);
        $this->assertNull($order->coupon_id);
    }

    // --- Cupom restrito por meio de pagamento ---

    #[Test]
    public function checkout_blocks_coupon_restricted_to_payment_method_when_none_informed(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PIXONLY',
            'type' => 'fixed',
            'value' => 5,
            'allowed_payment_methods' => ['pix'],
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'PIXONLY'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_blocks_coupon_restricted_to_payment_method_when_a_different_one_informed(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PIXONLY2',
            'type' => 'fixed',
            'value' => 5,
            'allowed_payment_methods' => ['pix'],
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'coupon_code' => 'PIXONLY2',
                    'payment_method' => 'credit_card',
                ])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_applies_coupon_restricted_to_payment_method_when_the_matching_one_informed(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PIXOK',
            'type' => 'fixed',
            'value' => 5,
            'allowed_payment_methods' => ['pix'],
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'coupon_code' => 'PIXOK',
                    'payment_method' => 'pix',
                ])
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        $this->assertEquals(5.0, (float) $order->discount_amount);
    }

    #[Test]
    public function checkout_applies_coupon_without_payment_restriction_regardless_of_payment_method(): void
    {
        [$tenant, $product, $address] = $this->setupStore();

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'ANYMETHOD',
            'type' => 'fixed',
            'value' => 5,
        ]);

        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['coupon_code' => 'ANYMETHOD'])
            );

        $response->assertStatus(201);
    }

    // --- Prévia pública de cupom (POST /loja/{slug}/cupons/validar) ---

    #[Test]
    public function validate_endpoint_returns_discount_for_a_valid_percentage_coupon_without_known_customer(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['price' => 10]);

        Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'PREVIEW10',
            'type' => 'percentage',
            'value' => 10,
        ]);

        $response = $this->postJson('/api/v1/loja/' . $tenant->slug . '/cupons/validar', [
            'code' => 'PREVIEW10',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('data.type', 'percentage');
        $this->assertEquals(2.0, $response->json('data.discount_amount'));
    }

    #[Test]
    public function validate_endpoint_returns_422_with_invalid_coupon_code(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['price' => 10]);

        $response = $this->postJson('/api/v1/loja/' . $tenant->slug . '/cupons/validar', [
            'code' => 'DOESNOTEXIST',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'INVALID_COUPON');
    }

    #[Test]
    public function validate_endpoint_does_not_enforce_per_customer_limit(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $product = $this->createProduct($tenant->id, ['price' => 10]);

        $coupon = Coupon::create([
            'tenant_id' => $tenant->id,
            'code' => 'ANYCUSTOMER',
            'type' => 'fixed',
            'value' => 3,
            'max_uses_per_customer' => 1,
        ]);

        $client = $this->createClient($tenant->id);
        $order = Order::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $client->id,
            'stock_location_id' => $this->createLocation($tenant->id)->id,
            'codigo' => '1',
            'is_installment' => false,
            'total_amount' => 10,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'confirmed',
            'stock_reserved' => false,
        ]);

        $customer = FinalCustomer::create(['email' => 'ja-usou@coupon-checkout.test']);
        CouponRedemption::create([
            'tenant_id' => $tenant->id,
            'coupon_id' => $coupon->id,
            'final_customer_id' => $customer->id,
            'order_id' => $order->id,
            'redeemed_at' => now(),
        ]);

        // Prévia não sabe quem é o cliente — não bloqueia por limite por
        // cliente (só existe check de limite TOTAL/geral, que não foi
        // configurado neste cupom).
        $response = $this->postJson('/api/v1/loja/' . $tenant->slug . '/cupons/validar', [
            'code' => 'ANYCUSTOMER',
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3.0, $response->json('data.discount_amount'));
    }
}
