<?php

namespace Tests\Feature\Portal;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use App\Services\Auth\CustomerJWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * POST /portal/links — confirmação explícita de que o cliente final também
 * é o Client de uma loja específica, a partir de um sale_uuid que ele já
 * tem (mesmo espírito de posse do link de rastreio da Fase 5.1).
 */
class PortalLinkTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;

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

    private function createOrder(Tenant $tenant, FinalCustomer $client, array $overrides = []): Sale
    {

        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_completed' => false,
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
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $order->uuid]);

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant_name', $tenant->name);

        $this->assertDatabaseHas('final_customer_tenant_links', [
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
        ]);

        // 2 linhas no total: a do próprio $client (criada por
        // createClient(), CreatesSaleFixtures — já confirmada de
        // fábrica) + a nova, do $customer autenticado que acabou de
        // vincular via sale_uuid.
        $this->assertDatabaseCount('final_customer_tenant_links', 2);
    }

    #[Test]
    public function linking_the_same_order_twice_is_idempotent(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $order = $this->createOrder($tenant, $client);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $order->uuid])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $order->uuid])
            ->assertStatus(200);

        // Só 1 linha pro $customer autenticado (idempotente) — a segunda
        // linha do total é a do próprio $client (fixture já confirmada).
        $this->assertDatabaseCount('final_customer_tenant_links', 2);
        $this->assertSame(1, \App\Models\FinalCustomer\FinalCustomerTenantLink::where('final_customer_id', $customer->id)->count());
    }

    #[Test]
    public function a_second_order_from_the_same_store_reuses_the_existing_link(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $client = $this->createClient($tenant->id);
        $firstOrder = $this->createOrder($tenant, $client);
        $secondOrder = $this->createOrder($tenant, $client);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $firstOrder->uuid])
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => $secondOrder->uuid])
            ->assertStatus(200);

        $this->assertDatabaseCount('final_customer_tenant_links', 2);
        $this->assertSame(1, \App\Models\FinalCustomer\FinalCustomerTenantLink::where('final_customer_id', $customer->id)->count());
    }

    #[Test]
    public function rejects_a_nonexistent_sale_uuid(): void
    {
        [, $token] = $this->authenticatedCustomer();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['sale_uuid' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function requires_authentication(): void
    {
        $this->postJson('/api/v1/portal/links', ['sale_uuid' => (string) Str::uuid()])
            ->assertStatus(401);
    }
}
