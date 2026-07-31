<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Storefront\ProductPromotion;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Preço promocional "de/por" no checkout (Delivery Fase 3) —
 * StorefrontCheckoutService::resolveEffectiveUnitPrice(): promoção ativa
 * sempre vence sobre preço de categoria e preço base.
 */
class StorefrontCheckoutPromotionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@promo.test'): array
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
                ['product_uuid' => $productUuid, 'quantity' => 2],
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

    #[Test]
    public function checkout_uses_promo_price_instead_of_base_price(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'promo_price' => 12,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // 2 x R$12,00 (preço promocional, não os R$20,00 base) = R$24,00.
        $this->assertEquals(24.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_promo_price_wins_over_category_discount_price(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'promo_price' => 12,
        ]);

        [$customer, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        // Primeira compra: cria o Client/Link.
        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            )->assertStatus(201);

        $client = Order::where('uuid', $first->json('data.order.uuid'))->firstOrFail()->client;

        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $second->assertStatus(201);

        $order = Order::where('uuid', $second->json('data.order.uuid'))->firstOrFail();

        // 2 x R$12,00 (promoção ainda ativa na recompra) = R$24,00.
        $this->assertEquals(24.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_uses_percentage_promotion_over_current_product_price(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'discount_type' => 'percentage',
            'discount_percentage' => 25,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // 25% off R$20,00 = R$15,00; 2 x R$15,00 = R$30,00. Calculado sobre
        // o Product.price VIGENTE no momento do checkout, não congelado.
        $this->assertEquals(30.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_falls_back_to_base_price_when_promotion_is_expired(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 20]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'promo_price' => 12,
            'expires_at' => now()->subDay(),
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        // Promoção expirada: usa o preço base (2 x R$20,00 = R$40,00).
        $this->assertEquals(40.0, (float) $order->total_amount);
    }
}
