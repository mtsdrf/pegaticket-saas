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
 * POST /portal/links — confirmação explícita de que o cliente final também
 * é o Client de uma loja específica, a partir de um order_uuid que ele já
 * tem (mesmo espírito de posse do link de rastreio da Fase 5.1).
 */
class PortalLinkTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant ' . Str::random(6),
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
    public function creates_the_link_for_the_order_tenant_and_client(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $order->uuid]);

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_name', $tenant->name)
            ->assertJsonPath('data.client_name', $client->name);

        $this->assertDatabaseHas('final_customer_tenant_links', [
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        $this->assertDatabaseCount('final_customer_tenant_links', 1);
    }

    #[Test]
    public function linking_the_same_order_twice_is_idempotent(): void
    {
        [, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $order->uuid])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $order->uuid])
            ->assertStatus(200);

        $this->assertDatabaseCount('final_customer_tenant_links', 1);
    }

    #[Test]
    public function a_second_order_from_the_same_store_reuses_the_existing_link(): void
    {
        [, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $firstOrder = $this->createOrder($tenant, $client);
        $secondOrder = $this->createOrder($tenant, $client);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $firstOrder->uuid])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $secondOrder->uuid])
            ->assertStatus(200);

        $this->assertDatabaseCount('final_customer_tenant_links', 1);
    }

    #[Test]
    public function rejects_a_nonexistent_order_uuid(): void
    {
        [, $token] = $this->authenticatedCustomer();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/v1/portal/links', ['order_uuid' => (string) Str::uuid()])
            ->assertStatus(401);
    }
}
