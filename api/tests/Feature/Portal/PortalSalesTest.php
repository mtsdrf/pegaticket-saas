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
 * GET /portal/sales (histórico agregado, só lojas vinculadas E
 * confirmadas) e GET /portal/me (perfil + lojas vinculadas).
 */
class PortalSalesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSaleFixtures;

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

    /**
     * `$owner` é o FinalCustomer autenticado no portal (order.final_customer_id
     * referencia final_customers diretamente desde 2026-07-31) — diferente
     * do fluxo antigo, não existe mais um Client tenant-scoped intermediário.
     */
    private function createOrder(Tenant $tenant, FinalCustomer $owner, array $overrides = []): Sale
    {

        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $owner->id,
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
        $linkedOrder = $this->createOrder($linkedTenant, $customer);

        $unlinkedTenant = $this->createTenant('Loja Não Vinculada');
        $this->createOrder($unlinkedTenant, $customer);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $linkedOrder->uuid])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales');

        $response->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($linkedOrder->uuid));
        $this->assertCount(1, $uuids);
        $this->assertSame($linkedTenant->slug, $response->json('data.0.tenant_slug'));
    }

    #[Test]
    public function orders_are_empty_when_no_store_is_linked_yet(): void
    {
        [$customer, $token] = $this->authenticatedCustomer();

        $tenant = $this->createTenant();
        $this->createOrder($tenant, $customer);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/sales');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function me_returns_profile_and_linked_stores(): void
    {
        [$customer, $token] = $this->authenticatedCustomer('perfil@test.com');

        $tenant = $this->createTenant('Loja X');
        $order = $this->createOrder($tenant, $customer);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/portal/links', ['order_uuid' => $order->uuid])
            ->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/portal/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'perfil@test.com')
            ->assertJsonPath('data.linked_stores.0.tenant_name', 'Loja X');
    }

    #[Test]
    public function orders_and_me_require_authentication(): void
    {
        $this->getJson('/api/v1/portal/sales')->assertStatus(401);
        $this->getJson('/api/v1/portal/me')->assertStatus(401);
    }
}
