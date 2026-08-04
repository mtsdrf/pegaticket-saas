<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class FinanceOperationsTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('finance-operations@test.com');
        $this->grantPermission('finance', 'read');
    }

    #[Test]
    public function dashboard_returns_balances_queues_and_upcoming_settlement(): void
    {
        [$event, $releasedReceivable, $scheduledSettlement] = $this->createFinanceFixture();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.balances.released_amount', 95)
            ->assertJsonPath('data.balances.in_custody_amount', 45)
            ->assertJsonPath('data.balances.future_amount', 45)
            ->assertJsonPath('data.queues.open_adjustments_count', 1)
            ->assertJsonPath('data.upcoming_settlement.uuid', $scheduledSettlement->uuid);

        $this->assertNotNull($event->id);
        $this->assertNotNull($releasedReceivable->id);
    }

    #[Test]
    public function receivables_endpoints_list_and_summarize_financial_receivables(): void
    {
        [$event, $releasedReceivable] = $this->createFinanceFixture();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/finance/receivables?event_uuid={$event->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.uuid', $releasedReceivable->uuid);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/finance/receivables/summary?event_uuid={$event->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.total_net_amount', 140);
    }

    #[Test]
    public function settlements_endpoints_list_and_summarize_settlements(): void
    {
        [, , $scheduledSettlement] = $this->createFinanceFixture();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/settlements')
            ->assertStatus(200)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.uuid', $scheduledSettlement->uuid);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/settlements/summary')
            ->assertStatus(200)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.total_net_amount', 140);
    }

    private function createFinanceFixture(): array
    {
        $product = $this->createProduct($this->tenant->id, ['price' => 100]);
        $event = $product->event;
        $client = $this->createClient($this->tenant->id);

        $saleA = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-FIN-'.Str::upper(Str::random(4)),
            'total_amount' => 100,
            'paid_amount' => 100,
            'is_paid' => true,
            'paid_at' => now()->subDay(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleA->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $saleB = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-FIN-'.Str::upper(Str::random(4)),
            'total_amount' => 50,
            'paid_amount' => 50,
            'is_paid' => true,
            'paid_at' => now()->subDay(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleB->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $releasedSettlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-FIN-REL-'.Str::upper(Str::random(4)),
            'status' => 'released',
            'scheduled_for' => now()->subDay(),
            'released_at' => now()->subHours(2),
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
        ]);

        $scheduledSettlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-FIN-SCH-'.Str::upper(Str::random(4)),
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
            'gross_amount' => 50,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 45,
        ]);

        $releasedReceivable = Receivable::create([
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleA->id,
            'event_id' => $event->id,
            'settlement_id' => $releasedSettlement->id,
            'status' => 'released',
            'currency' => 'BRL',
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => now()->subHours(4),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => 'SPLI_'.Str::upper(Str::random(8)),
        ]);

        $scheduledReceivable = Receivable::create([
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleB->id,
            'event_id' => $event->id,
            'settlement_id' => $scheduledSettlement->id,
            'status' => 'awaiting_release',
            'currency' => 'BRL',
            'gross_amount' => 50,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 45,
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => now()->addHours(5),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => 'SPLI_'.Str::upper(Str::random(8)),
        ]);

        SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $scheduledSettlement->id,
            'receivable_id' => $scheduledReceivable->id,
            'sale_id' => $scheduledReceivable->sale_id,
            'type' => 'refund_platform_exposure',
            'amount' => 10,
            'reason' => 'Exposição aguardando decisão.',
            'status' => 'pending_review',
        ]);

        return [$event->fresh(), $releasedReceivable->fresh(), $scheduledSettlement->fresh()];
    }
}
