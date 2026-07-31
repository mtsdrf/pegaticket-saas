<?php

namespace Tests\Feature\Portal;

use App\Models\Client\Client;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

/**
 * GET /portal/orders (histórico agregado, só lojas vinculadas E
 * confirmadas) e GET /portal/me (perfil + lojas vinculadas).
 */
class PortalOrdersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

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

    private function createOrder(Tenant $tenant, Client $client, array $overrides = []): Order
    {
        $location = $this->createLocation($tenant->id);

        return Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));
    }

    private function authenticatedCustomer(string $email = 'cliente@test.com'): array
    {
        $customer = FinalCustomer::create(['email' => $email]);
        $token = app(CustomerJWTService::class)->issueAccessToken($customer);

        return [$customer, $token];
    }

    #[Test]
    public function orders_only_include_linked_and_confirmed_stores(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $linkedTenant = $this->createTenant('Loja Vinculada');
        $linkedClient = $this->createClient($linkedTenant->id);
        $linkedOrder = $this->createOrder($linkedTenant, $linkedClient);

        $unlinkedTenant = $this->createTenant('Loja Não Vinculada');
        $unlinkedClient = $this->createClient($unlinkedTenant->id);
        $this->createOrder($unlinkedTenant, $unlinkedClient);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $linkedOrder->uuid])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/orders');

        $response->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($linkedOrder->uuid));
        $this->assertCount(1, $uuids);
        $this->assertSame($linkedTenant->slug, $response->json('data.0.tenant_slug'));
    }

    #[Test]
    public function orders_are_empty_when_no_store_is_linked_yet(): void
    {
        [, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $this->createOrder($tenant, $client);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/orders');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function me_returns_profile_and_linked_stores(): void
    {
        [, $token] = $this->authenticatedCustomer('perfil@test.com');

        $tenant = $this->createTenant('Loja X');
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $order->uuid])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'perfil@test.com')
            ->assertJsonPath('data.linked_stores.0.tenant_name', 'Loja X')
            ->assertJsonPath('data.linked_stores.0.client_name', $client->name);
    }

    #[Test]
    public function orders_and_me_require_authentication(): void
    {
        $this->getJson('/api/v1/portal/orders')->assertStatus(401);
        $this->getJson('/api/v1/portal/me')->assertStatus(401);
    }
}
