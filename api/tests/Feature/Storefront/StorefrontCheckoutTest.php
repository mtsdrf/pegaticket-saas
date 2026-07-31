<?php

namespace Tests\Feature\Storefront;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
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
 * Checkout da loja pública (Delivery Fase 1) — POST /loja/{slug}/checkout,
 * autenticado como FinalCustomer via customer.jwt. Reaproveita 100% a
 * lógica de preço/estoque/criação de OrderService::create() por baixo, ver
 * App\Services\Storefront\StorefrontCheckoutService.
 */
class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;
    use CreatesStorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout sempre cria um Endereco na primeira compra do cliente
        // final (StorefrontCheckoutService::ensureCustomerLink() ->
        // EnderecoService::create()), que sem lat/lng manual dispara
        // GeocodeEnderecoJob síncrono em testing — nenhum teste aqui
        // verifica lat/lng, só precisa não bater na API real.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);
    }

    private function authenticatedCustomer(string $email = 'cliente@storefront.test'): array
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

    #[Test]
    public function checkout_creates_client_endereco_and_link_on_first_purchase(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 25]);
        [$customer, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $this->assertDatabaseCount('final_customer_tenant_links', 0);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            );

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['order' => ['uuid']]]);

        $orderUuid = $response->json('data.order.uuid');
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        $this->assertSame('storefront', $order->origin);
        $this->assertSame('pending_approval', $order->status);
        $this->assertFalse((bool) $order->stock_reserved);
        $this->assertEquals(50, (float) $order->total_amount);
        $this->assertSame($customer->id, $order->final_customer_id);

        $this->assertDatabaseCount('final_customer_tenant_links', 1);
        $this->assertDatabaseHas('final_customer_tenant_links', [
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'phone_primary' => '11999998888',
            'is_trusted' => false,
        ]);

        $link = \App\Models\FinalCustomer\FinalCustomerTenantLink::where('final_customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $this->assertNotNull($link->confirmed_at);
        $this->assertNotNull($link->endereco_id);
    }

    #[Test]
    public function checkout_reuses_existing_link_and_client_on_second_purchase_same_tenant(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [$customer, $token] = $this->authenticatedCustomer('repetido@storefront.test');
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            )->assertStatus(201);

        $firstOrder = Order::where('uuid', $first->json('data.order.uuid'))->firstOrFail();

        $this->assertDatabaseCount('final_customer_tenant_links', 1);

        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['client_name' => 'Nome Diferente'])
            )->assertStatus(201);

        $secondOrder = Order::where('uuid', $second->json('data.order.uuid'))->firstOrFail();

        // Não cria um segundo Endereco/Link — reaproveita o mesmo
        // FinalCustomerTenantLink já confirmado, e os dois pedidos
        // pertencem ao mesmo FinalCustomer (identidade global).
        $this->assertDatabaseCount('final_customer_tenant_links', 1);
        $this->assertSame($customer->id, $firstOrder->final_customer_id);
        $this->assertSame($customer->id, $secondOrder->final_customer_id);
    }

    /**
     * A unique em final_customer_tenant_links é (final_customer_id,
     * tenant_id) — impede 2 linhas de link pro mesmo par cliente+loja,
     * mesmo em requisições concorrentes na primeira compra (a corrida de
     * verdade, com 2 requisições simultâneas, não é reproduzível de forma
     * determinística num teste de Feature single-threaded).
     */
    #[Test]
    public function final_customer_tenant_link_unique_constraint_prevents_duplicate_pair(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        [$customer] = $this->authenticatedCustomer();

        \App\Models\FinalCustomer\FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'confirmed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\FinalCustomer\FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'confirmed_at' => now(),
        ]);
    }

    #[Test]
    public function checkout_returns_404_when_plan_does_not_allow_storefront(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(false);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            )->assertStatus(404);
    }

    #[Test]
    public function checkout_returns_422_when_storefront_is_disabled_in_settings(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'storefront_enabled' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address)
            )
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOREFRONT_DISABLED');
    }

    #[Test]
    public function checkout_persists_a_note_per_item(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 25]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'items' => [
                        ['ticket_type_uuid' => $product->uuid, 'quantity' => 2, 'notes' => 'Bem passado'],
                    ],
                ])
            );

        $response->assertStatus(201);

        $orderUuid = $response->json('data.order.uuid');
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'ticket_type_id' => $product->id,
            'notes' => 'Bem passado',
        ]);
    }

    #[Test]
    public function checkout_persists_payment_method_and_change_for_amount(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 25]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'payment_method' => 'cash',
                    'needs_change' => true,
                    'change_for_amount' => 50,
                ])
            );

        $response->assertStatus(201);

        $orderUuid = $response->json('data.order.uuid');
        $order = Order::where('uuid', $orderUuid)->firstOrFail();

        $this->assertSame('cash', $order->payment_method);
        $this->assertTrue((bool) $order->needs_change);
        $this->assertEquals(50, (float) $order->change_for_amount);
    }

    #[Test]
    public function checkout_returns_422_when_needs_change_without_change_for_amount(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 25]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'payment_method' => 'cash',
                    'needs_change' => true,
                ])
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['change_for_amount']);
    }

    #[Test]
    public function checkout_returns_401_without_authentication(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id);
        $address = $this->createAddressTrio();

        $this->postJson(
            '/api/v1/loja/' . $tenant->slug . '/checkout',
            $this->checkoutPayload($product->uuid, $address)
        )->assertStatus(401);
    }

    #[Test]
    public function checkout_with_block_order_without_stock_true_and_insufficient_stock_returns_422_and_creates_no_order(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'items' => [['ticket_type_uuid' => $product->uuid, 'quantity' => 5]],
                ])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function checkout_with_block_order_without_stock_false_creates_order_even_without_stock(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        // block_order_without_stock=false é o default (migration) — nem
        // precisa criar TenantSettings explicitamente, mas fica explícito
        // aqui pra deixar a intenção do teste clara.
        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, [
                    'items' => [['ticket_type_uuid' => $product->uuid, 'quantity' => 5]],
                ])
            );

        $response->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order.uuid'))->firstOrFail();
        $this->assertFalse((bool) $order->stock_reserved);
        // reserveStock=false: nenhuma reserva de estoque é criada (sem
        // entrada nem reserva neste teste, o pedido nasce mesmo assim).
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function checkout_returns_422_when_client_last_name_is_missing(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        $payload = $this->checkoutPayload($product->uuid, $address);
        unset($payload['client_last_name']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/loja/' . $tenant->slug . '/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_last_name']);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Configurador de formas de entrega — simétrico dos testes de pickup
     * em StorefrontCheckoutPickupTest: tenant desabilitou explicitamente
     * tenant_settings.allow_delivery, checkout com fulfillment_type=
     * delivery (default) deve ser bloqueado.
     */
    #[Test]
    public function checkout_with_delivery_is_blocked_when_tenant_disabled_it(): void
    {
        $tenant = $this->createTenantWithStorefrontPlan(true);
        $this->makeStoreOpenAllDay($tenant->id);
        $this->createLocation($tenant->id, ['is_default' => true]);
        $product = $this->createProduct($tenant->id, ['price' => 10]);
        [, $token] = $this->authenticatedCustomer();
        $address = $this->createAddressTrio();
        $this->createDeliveryFeeForBairro($tenant->id, $address[2]);

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'send_tracking_link_whatsapp' => false,
            'block_order_without_stock' => false,
            'allow_store_pickup' => true,
            'allow_delivery' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                '/api/v1/loja/' . $tenant->slug . '/checkout',
                $this->checkoutPayload($product->uuid, $address, ['fulfillment_type' => 'delivery'])
            );

        $response->assertStatus(422)->assertJsonPath('code', 'DELIVERY_UNAVAILABLE');
        $this->assertDatabaseCount('orders', 0);
    }
}
