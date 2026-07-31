<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Tenant\TenantSettings;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Storefront\Concerns\CreatesStorefrontFixtures;
use Tests\TestCase;

/**
 * Retirada na loja (roadmap Delivery, pickup) — checkout público com
 * fulfillment_type=pickup não exige endereço de entrega nem taxa por
 * bairro (pula o Guard 3 inteiro). Ver
 * App\Services\Storefront\StorefrontCheckoutService::checkout().
 */
class StorefrontCheckoutPickupTest extends TestCase
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

    private function authenticatedCustomer(string $email = 'cliente@pickup.test'): array
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

    private function giveTenantOwnAddress(int $tenantId): void
    {
        [$estado, $cidade, $bairro] = $this->createAddressTrio();

        $endereco = Endereco::create([
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua da Própria Loja, 1',
        ]);

        \App\Models\Tenant\Tenant::where('id', $tenantId)->update(['endereco_id' => $endereco->id]);
    }

    private function setupOpenStoreWithPickup(bool $allowStorePickup = true): array
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'allow_store_pickup' => $allowStorePickup,
        ]);

        return [$tenant, $product];
    }

    #[Test]
    public function checkout_with_pickup_does_not_require_delivery_address_and_creates_order(): void
    {
        [$tenant, $product] = $this->setupOpenStoreWithPickup();
        $this->giveTenantOwnAddress($tenant->id);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', [
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 2],
                ],
                'client_name' => 'Cliente Retirada',
                'client_last_name' => 'Sobrenome',
                'client_phone' => '11999998888',
                'fulfillment_type' => 'pickup',
            ]);

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();

        $this->assertEquals('pickup', $order->fulfillment_type);
        $this->assertEquals(0.0, (float) $order->delivery_fee);
        // 2 x R$10, sem taxa de entrega.
        $this->assertEquals(20.0, (float) $order->total_amount);
    }

    #[Test]
    public function checkout_with_pickup_is_blocked_when_tenant_did_not_enable_it(): void
    {
        [$tenant, $product] = $this->setupOpenStoreWithPickup(false);
        $this->giveTenantOwnAddress($tenant->id);
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', [
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Cliente Retirada',
                'client_last_name' => 'Sobrenome',
                'client_phone' => '11999998888',
                'fulfillment_type' => 'pickup',
            ]);

        $response->assertStatus(422)->assertJsonPath('code', 'STORE_PICKUP_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_with_pickup_is_blocked_when_store_has_no_address_configured(): void
    {
        [$tenant, $product] = $this->setupOpenStoreWithPickup(true);
        // Loja habilitou pickup mas NÃO configurou endereço próprio ainda.
        [, $token] = $this->authenticatedCustomer();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', [
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Cliente Retirada',
                'client_last_name' => 'Sobrenome',
                'client_phone' => '11999998888',
                'fulfillment_type' => 'pickup',
            ]);

        $response->assertStatus(422)->assertJsonPath('code', 'STORE_PICKUP_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Regressão: checkout sem fulfillment_type nenhum no payload (cliente
     * antigo do frontend) continua se comportando como delivery — endereço
     * continua obrigatório, taxa de entrega continua sendo cobrada.
     */
    #[Test]
    public function checkout_without_fulfillment_type_field_still_behaves_as_delivery(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();

        // Sem estado_uuid/cidade_uuid/bairro_uuid/logradouro e sem
        // fulfillment_type: deve cair no required_if de delivery.
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', [
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Cliente Loja',
                'client_last_name' => 'Sobrenome',
                'client_phone' => '11999998888',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['estado_uuid', 'cidade_uuid', 'bairro_uuid', 'logradouro']);
    }

    #[Test]
    public function checkout_with_explicit_delivery_still_requires_address_and_delivery_fee(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2], 5.0);

        [$estado, $cidade, $bairro] = $address;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', [
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
                'client_name' => 'Cliente Loja',
                'client_last_name' => 'Sobrenome',
                'client_phone' => '11999998888',
                'fulfillment_type' => 'delivery',
                'estado_uuid' => $estado->uuid,
                'cidade_uuid' => $cidade->uuid,
                'bairro_uuid' => $bairro->uuid,
                'logradouro' => 'Rua Teste, 1',
            ]);

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        $this->assertEquals('delivery', $order->fulfillment_type);
        $this->assertEquals(5.0, (float) $order->delivery_fee);
    }
}
