<?php

namespace Tests\Feature\FinalCustomer;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class FinalCustomerListTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('final-customer-user@test.com');
    }

    private function createLink(int $tenantId, string $name, string $lastName, string $email, ?string $cpfCnpj = null, bool $isActive = true): FinalCustomerTenantLink
    {
        $customer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'last_name' => $lastName,
            'email' => $email,
        ]);

        return FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $customer->id,
            'tenant_id' => $tenantId,
            'cpf_cnpj' => $cpfCnpj,
            'is_active' => $isActive,
            'is_trusted' => true,
            'confirmed_at' => now(),
        ]);
    }

    #[Test]
    public function staff_without_permission_receives_403(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/final-customers')
            ->assertStatus(403);
    }

    #[Test]
    public function staff_with_permission_searches_by_name_and_gets_only_own_tenant_results(): void
    {
        $this->grantPermission('customers', 'read');

        $this->createLink($this->tenant->id, 'Alice', 'Souza', 'alice@example.com');

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->createLink($otherTenant->id, 'Alice', 'Foreign', 'alice.foreign@example.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/final-customers?search=Alice')
            ->assertStatus(200);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.final_customer.email', 'alice@example.com');
    }

    #[Test]
    public function staff_with_permission_searches_by_email(): void
    {
        $this->grantPermission('customers', 'read');

        $this->createLink($this->tenant->id, 'Bruno', 'Lima', 'bruno.lima@example.com');
        $this->createLink($this->tenant->id, 'Carla', 'Melo', 'carla.melo@example.com');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/final-customers?search=bruno.lima@example.com')
            ->assertStatus(200);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.final_customer.name', 'Bruno');
    }

    #[Test]
    public function inactive_links_are_not_returned(): void
    {
        $this->grantPermission('customers', 'read');

        $this->createLink($this->tenant->id, 'Diego', 'Costa', 'diego@example.com', null, false);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/final-customers')
            ->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    }
}
