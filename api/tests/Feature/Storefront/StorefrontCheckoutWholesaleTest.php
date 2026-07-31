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
 * Preço de atacado por quantidade no checkout (roadmap Loja) —
 * StorefrontCheckoutService::resolveEffectiveUnitPrice(): promoção vence
 * sobre tudo; preço de categoria vence sobre atacado (atacado só pra quem
 * NÃO tem categoria); abaixo do mínimo usa preço base.
 */
class StorefrontCheckoutWholesaleTest extends TestCase
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

    private function authenticatedCustomer(string $email = 'cliente@atacado.test'): array
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

    private function checkoutPayload(string $productUuid, float $quantity, array $address): array
    {
        [$estado, $cidade, $bairro] = $address;

        return [
            'items' => [
                ['product_uuid' => $productUuid, 'quantity' => $quantity],
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
        ];
    }

    private function checkout(string $slug, string $token, array $payload): Order
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $slug . '/checkout', $payload)
            ->assertStatus(201);

        return Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
    }

    #[Test]
    public function customer_without_category_meeting_minimum_uses_wholesale_price(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, [
            'price' => 20,
            'wholesale_min_quantity' => 10,
            'wholesale_price' => 15,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $order = $this->checkout($tenant->slug, $token, $this->checkoutPayload($product->uuid, 10, $address));

        // 10 x R$15,00 (atacado, quantidade >= mínimo) = R$150,00.
        $this->assertEquals(150.0, (float) $order->total_amount);
    }

    #[Test]
    public function quantity_below_minimum_uses_base_price(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, [
            'price' => 20,
            'wholesale_min_quantity' => 10,
            'wholesale_price' => 15,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $order = $this->checkout($tenant->slug, $token, $this->checkoutPayload($product->uuid, 9, $address));

        // 9 x R$20,00 (abaixo do mínimo de 10, usa preço base) = R$180,00.
        $this->assertEquals(180.0, (float) $order->total_amount);
    }

    #[Test]
    public function customer_with_applicable_category_ignores_wholesale_even_with_enough_quantity(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, [
            'price' => 20,
            'wholesale_min_quantity' => 10,
            'wholesale_price' => 15,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        // Primeira compra: cria o Client/Link.
        $first = $this->checkout($tenant->slug, $token, $this->checkoutPayload($product->uuid, 10, $address));
        $client = $first->client;

        // Preço de categoria (R$18,00) — vence sobre o atacado (R$15,00),
        // mesmo com quantidade suficiente pro atacado (categoria sempre vence).
        $category = $this->createClientCategory($tenant->id);
        $this->attachClientCategory($client, $category);
        $this->setProductCategoryPrice($product, $category, 18);

        $order = $this->checkout($tenant->slug, $token, $this->checkoutPayload($product->uuid, 10, $address));

        // 10 x R$18,00 (categoria, ignora atacado) = R$180,00.
        $this->assertEquals(180.0, (float) $order->total_amount);
    }

    #[Test]
    public function promotion_wins_over_wholesale(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, [
            'price' => 20,
            'wholesale_min_quantity' => 10,
            'wholesale_price' => 15,
        ]);

        ProductPromotion::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'promo_price' => 12,
        ]);

        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $order = $this->checkout($tenant->slug, $token, $this->checkoutPayload($product->uuid, 10, $address));

        // 10 x R$12,00 (promoção, vence sobre o atacado de R$15,00) = R$120,00.
        $this->assertEquals(120.0, (float) $order->total_amount);
    }
}
