<?php

namespace Tests\Feature\Orders;

use App\Models\Client\Client;
use App\Models\Order\Order;
use App\Models\Order\OrderPrepLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Link temporário de preparo (roadmap Loja): POST /storefront-orders/
 * {order}/prep-link (staff, perm:storefront-orders,read) gera o token;
 * GET /storefront-orders/{uuid}/prep?token= (público) lê o pedido. 404
 * genérico pra token errado/expirado/de outro pedido/pedido inexistente.
 */
class OrderPrepLinkTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('prep-link-user@test.com');
        $this->grantPermission('storefront-orders', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createOrderWithClient(): Order
    {
        $client = $this->createClient($this->tenant->id);
        $client->phone_primary = '11988887777';
        $client->save();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
            'origin' => 'storefront',
            'status' => 'pending_approval',
        ]);
    }

    private function generateToken(Order $order): string
    {
        $response = $this->auth()
            ->postJson('/api/v1/storefront-orders/' . $order->uuid . '/prep-link')
            ->assertStatus(201);

        return $response->json('data.token');
    }

    #[Test]
    public function valid_token_returns_order_data_with_client_address_and_phone(): void
    {
        $order = $this->createOrderWithClient();
        $token = $this->generateToken($order);

        $response = $this->getJson('/api/v1/storefront-orders/' . $order->uuid . '/prep?token=' . $token);

        $response->assertStatus(200)
            ->assertJsonPath('data.uuid', $order->uuid)
            ->assertJsonPath('data.client.phone_primary', '11988887777')
            ->assertJsonPath('data.client.endereco.logradouro', 'Rua Teste, 123');

        $this->assertNotNull($response->json('data.client.endereco.bairro_name'));
        $this->assertNotNull($response->json('data.client.endereco.cidade_name'));
    }

    #[Test]
    public function expired_token_returns_404(): void
    {
        $order = $this->createOrderWithClient();

        $link = OrderPrepLink::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'token' => Str::random(48),
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/storefront-orders/' . $order->uuid . '/prep?token=' . $link->token)
            ->assertStatus(404);
    }

    #[Test]
    public function token_of_another_order_returns_404(): void
    {
        $orderA = $this->createOrderWithClient();
        $orderB = $this->createOrderWithClient();

        $tokenA = $this->generateToken($orderA);

        $this->getJson('/api/v1/storefront-orders/' . $orderB->uuid . '/prep?token=' . $tokenA)
            ->assertStatus(404);
    }

    #[Test]
    public function nonexistent_order_returns_404(): void
    {
        $this->getJson('/api/v1/storefront-orders/' . Str::uuid() . '/prep?token=' . Str::random(48))
            ->assertStatus(404);
    }

    #[Test]
    public function wrong_token_for_existing_order_returns_404(): void
    {
        $order = $this->createOrderWithClient();
        $this->generateToken($order);

        $this->getJson('/api/v1/storefront-orders/' . $order->uuid . '/prep?token=' . Str::random(48))
            ->assertStatus(404);
    }

    #[Test]
    public function generating_prep_link_requires_storefront_orders_read_permission(): void
    {
        $this->setUpTenantScopedUser('no-perm-prep-user@test.com');
        $order = $this->createOrderWithClient();

        $this->auth()
            ->postJson('/api/v1/storefront-orders/' . $order->uuid . '/prep-link')
            ->assertStatus(403);
    }
}
