<?php

namespace Tests\Feature\FinalCustomer;

use App\Models\Sale\Sale;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * CRM básico do comprador (Fase 6, fatia final) — agregação de total
 * gasto/quantidade de compras/última compra a partir de vendas já
 * existentes, com filtros de segmentação simples (min_spent, min_purchases,
 * inactive_days).
 */
class FinalCustomerCrmTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('crm-user@test.com');
        $this->grantPermission('customers', 'read');
        $this->grantPermission('sales', 'create');
    }

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function issuePaidSale(string $finalCustomerUuid, float $price): Sale
    {
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $response = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $finalCustomerUuid,
            'is_installment' => false,
            'mark_as_paid' => true,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->assertStatus(201)->json('data');

        return Sale::where('uuid', $response['uuid'])->firstOrFail();
    }

    #[Test]
    public function lists_customers_with_total_spent_and_purchase_count_correctly_aggregated(): void
    {
        $frequentBuyer = $this->createClient($this->tenant->id);
        $this->issuePaidSale($frequentBuyer->uuid, 60);
        $this->issuePaidSale($frequentBuyer->uuid, 90);

        $response = $this->auth()->getJson('/api/v1/final-customers/crm')->assertStatus(200);

        $entry = collect($response->json('data'))->firstWhere('final_customer_uuid', $frequentBuyer->uuid);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(150.0, $entry['total_spent'], 0.001);
        $this->assertSame(2, $entry['purchase_count']);
        $this->assertNotNull($entry['last_purchase_at']);
    }

    #[Test]
    public function lists_customers_with_no_purchases_showing_zeroed_totals(): void
    {
        $neverBought = $this->createClient($this->tenant->id);

        $response = $this->auth()->getJson('/api/v1/final-customers/crm')->assertStatus(200);

        $entry = collect($response->json('data'))->firstWhere('final_customer_uuid', $neverBought->uuid);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.0, $entry['total_spent'], 0.001);
        $this->assertSame(0, $entry['purchase_count']);
        $this->assertNull($entry['last_purchase_at']);
    }

    #[Test]
    public function filters_by_minimum_spent(): void
    {
        $bigSpender = $this->createClient($this->tenant->id);
        $this->issuePaidSale($bigSpender->uuid, 200);

        $smallSpender = $this->createClient($this->tenant->id);
        $this->issuePaidSale($smallSpender->uuid, 10);

        $response = $this->auth()->getJson('/api/v1/final-customers/crm?min_spent=100')->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('final_customer_uuid');

        $this->assertTrue($uuids->contains($bigSpender->uuid));
        $this->assertFalse($uuids->contains($smallSpender->uuid));
    }

    #[Test]
    public function filters_by_minimum_purchase_count(): void
    {
        $repeatBuyer = $this->createClient($this->tenant->id);
        $this->issuePaidSale($repeatBuyer->uuid, 30);
        $this->issuePaidSale($repeatBuyer->uuid, 30);

        $oneTimeBuyer = $this->createClient($this->tenant->id);
        $this->issuePaidSale($oneTimeBuyer->uuid, 30);

        $response = $this->auth()->getJson('/api/v1/final-customers/crm?min_purchases=2')->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('final_customer_uuid');

        $this->assertTrue($uuids->contains($repeatBuyer->uuid));
        $this->assertFalse($uuids->contains($oneTimeBuyer->uuid));
    }

    #[Test]
    public function filters_by_inactive_days_since_last_purchase(): void
    {
        $dormantBuyer = $this->createClient($this->tenant->id);
        $dormantSale = $this->issuePaidSale($dormantBuyer->uuid, 40);
        Sale::whereKey($dormantSale->id)->update(['paid_at' => now()->subDays(90)]);

        $recentBuyer = $this->createClient($this->tenant->id);
        $this->issuePaidSale($recentBuyer->uuid, 40);

        $response = $this->auth()->getJson('/api/v1/final-customers/crm?inactive_days=60')->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('final_customer_uuid');

        $this->assertTrue($uuids->contains($dormantBuyer->uuid));
        $this->assertFalse($uuids->contains($recentBuyer->uuid));
    }

    #[Test]
    public function never_returns_customers_belonging_to_another_tenant(): void
    {
        $otherTenantUser = $this->setUpOtherTenantWithCustomer();

        $response = $this->auth()->getJson('/api/v1/final-customers/crm')->assertStatus(200);
        $uuids = collect($response->json('data'))->pluck('final_customer_uuid');

        $this->assertFalse($uuids->contains($otherTenantUser));
    }

    private function setUpOtherTenantWithCustomer(): string
    {
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $customer = $this->createClient($otherTenant->id);

        return $customer->uuid;
    }
}
