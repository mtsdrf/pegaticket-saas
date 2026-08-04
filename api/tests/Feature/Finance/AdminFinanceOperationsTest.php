<?php

namespace Tests\Feature\Finance;

use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use App\Models\Event\TicketType;
use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class AdminFinanceOperationsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('admin-finance-operations@test.com');
        $this->grantPermission('payment_admin', 'read');
    }

    #[Test]
    public function admin_dashboard_returns_cross_tenant_financial_snapshot(): void
    {
        [$tenantA, $scheduledSettlement] = $this->createCrossTenantFinanceFixture('Alpha Tenant', 95, 10);
        [$tenantB] = $this->createCrossTenantFinanceFixture('Beta Tenant', 45, 0);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.balances.released_amount', 45)
            ->assertJsonPath('data.queues.open_adjustments_count', 1)
            ->assertJsonPath('data.upcoming_settlement.uuid', $scheduledSettlement->uuid)
            ->assertJsonPath('data.top_tenants_by_receivables.0.tenant.name', $tenantA->name);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/dashboard?tenant_uuid='.$tenantB->uuid)
            ->assertStatus(200)
            ->assertJsonPath('data.balances.released_amount', 45);
    }

    #[Test]
    public function admin_receivables_and_adjustments_are_listed_cross_tenant(): void
    {
        [$tenantA, , $receivableA] = $this->createCrossTenantFinanceFixture('Alpha Tenant', 95, 10);
        [$tenantB, , $receivableB] = $this->createCrossTenantFinanceFixture('Beta Tenant', 45, 0);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/receivables')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.tenant.name', $tenantA->name);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/receivables?tenant_uuid='.$tenantA->uuid)
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.uuid', $receivableA->uuid);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/adjustments')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.tenant.name', $tenantA->name);

        $this->assertNotNull($receivableB->id);
    }

    #[Test]
    public function admin_settlements_are_listed_cross_tenant(): void
    {
        [, $settlementA] = $this->createCrossTenantFinanceFixture('Alpha Tenant', 95, 0);
        [$tenantB, $settlementB] = $this->createCrossTenantFinanceFixture('Beta Tenant', 45, 0);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/settlements')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.uuid', $settlementB->uuid);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/admin/settlements?tenant_uuid='.$tenantB->uuid)
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.uuid', $settlementB->uuid);

        $this->assertNotNull($settlementA->id);
    }

    /**
     * @return array{0:Tenant,1:Settlement,2:Receivable}
     */
    private function createCrossTenantFinanceFixture(string $tenantName, float $netAmount, float $adjustmentAmount): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => $tenantName,
            'slug' => Str::slug($tenantName).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $category = EventCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Category '.Str::random(6),
            'is_active' => true,
        ]);

        $event = Event::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event_category_id' => $category->id,
            'name' => 'Event '.Str::random(6),
            'slug' => 'event-'.Str::random(8),
            'type' => 'ingresso',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'visibility' => 'public',
            'status' => 'publicado',
        ]);

        $ticketType = TicketType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'name' => 'Ticket '.Str::random(6),
            'price' => $netAmount + 5,
            'status' => 'ativo',
            'unit' => 'un',
            'min_per_order' => 1,
        ]);

        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Client '.Str::random(6),
            'email' => 'client-'.Str::random(8).'@test.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $tenant->id,
            'cpf_cnpj' => '12345678909',
            'phone_primary' => '11999999999',
            'is_trusted' => true,
            'is_active' => true,
            'confirmed_at' => now(),
        ]);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'final_customer_id' => $finalCustomer->id,
            'codigo' => 'SALE-ADM-'.Str::upper(Str::random(4)),
            'total_amount' => $netAmount + 5,
            'paid_amount' => $netAmount + 5,
            'is_paid' => true,
            'paid_at' => now()->subDay(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $netAmount + 5,
            'line_total' => $netAmount + 5,
        ]);

        $settlement = Settlement::create([
            'tenant_id' => $tenant->id,
            'code' => 'SET-ADM-'.Str::upper(Str::random(6)),
            'status' => $adjustmentAmount > 0 ? 'release_requested' : 'released',
            'scheduled_for' => $adjustmentAmount > 0 ? now()->addHours(2) : now()->subHours(2),
            'released_at' => $adjustmentAmount > 0 ? null : now()->subHour(),
            'gross_amount' => $netAmount + 5,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => $netAmount,
        ]);

        $receivable = Receivable::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $sale->id,
            'event_id' => $event->id,
            'settlement_id' => $settlement->id,
            'status' => $adjustmentAmount > 0 ? 'release_requested' : 'released',
            'currency' => 'BRL',
            'gross_amount' => $netAmount + 5,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => $netAmount,
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => $adjustmentAmount > 0 ? now()->addHour() : now()->subHours(3),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => 'SPLI_'.Str::upper(Str::random(8)),
        ]);

        if ($adjustmentAmount > 0) {
            SettlementAdjustment::create([
                'tenant_id' => $tenant->id,
                'settlement_id' => $settlement->id,
                'receivable_id' => $receivable->id,
                'sale_id' => $sale->id,
                'type' => 'refund_platform_exposure',
                'amount' => $adjustmentAmount,
                'reason' => 'Exposição aguardando decisão administrativa.',
                'status' => 'pending_review',
            ]);
        }

        return [$tenant, $settlement, $receivable];
    }
}
