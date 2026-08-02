<?php

namespace Tests\Feature\Sales;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Geração de link temporário de preparo (roadmap Loja): o endpoint staff
 * `POST /storefront-sales/{order}/prep-link` segue ativo para emissão do
 * token, mesmo após a remoção da view pública antiga de preparo.
 */
class SalePrepLinkTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('prep-link-user@test.com');
        $this->grantPermission('storefront-sales', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createOrderWithClient(): Sale
    {
        $client = $this->createClient($this->tenant->id);
        \App\Models\FinalCustomer\FinalCustomerTenantLink::where('final_customer_id', $client->id)
            ->where('tenant_id', $this->tenant->id)
            ->update(['phone_primary' => '11988887777']);


        return Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_completed' => false,
            'origin' => 'storefront',
            'status' => 'pending_approval',
        ]);
    }

    private function generateToken(Sale $order): string
    {
        $response = $this->auth()
            ->postJson('/api/v1/storefront-sales/' . $order->uuid . '/prep-link')
            ->assertStatus(201);

        return $response->json('data.token');
    }

    #[Test]
    public function prep_link_generation_returns_a_token_for_a_storefront_order(): void
    {
        $order = $this->createOrderWithClient();

        $response = $this->auth()
            ->postJson('/api/v1/storefront-sales/' . $order->uuid . '/prep-link')
            ->assertStatus(201);

        $this->assertIsString($response->json('data.token'));
        $this->assertNotSame('', $response->json('data.token'));
    }

    #[Test]
    public function generating_prep_link_requires_storefront_orders_read_permission(): void
    {
        $this->setUpTenantScopedUser('no-perm-prep-user@test.com');
        $order = $this->createOrderWithClient();

        $this->auth()
            ->postJson('/api/v1/storefront-sales/' . $order->uuid . '/prep-link')
            ->assertStatus(403);
    }
}
