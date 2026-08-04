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

class SettlementAdjustmentWorkflowTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('settlement-adjustment-workflow@test.com');
    }

    #[Test]
    public function manual_adjustment_endpoint_updates_balances_and_writes_ledger(): void
    {
        $this->grantPermission('finance', 'update');

        [$settlement, $receivable] = $this->createSettlementFixture();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/finance/adjustments/manual', [
                'receivable_uuid' => $receivable->uuid,
                'type' => 'manual_debit',
                'amount' => 10,
                'reason' => 'Correção operacional de bordereau.',
                'resolution_notes' => 'Desconto operacional validado pelo financeiro.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.type', 'manual_debit')
            ->assertJsonPath('data.amount', 10);

        $receivable->refresh();
        $settlement->refresh();

        $this->assertSame('85.00', $receivable->net_amount);
        $this->assertSame('85.00', $settlement->net_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'entry_type' => 'manual_debit',
            'direction' => 'debit',
            'amount' => 10,
        ]);
    }

    #[Test]
    public function resolve_pending_recovery_endpoint_marks_adjustment_as_recovered_and_writes_ledger(): void
    {
        $this->grantPermission('finance', 'update');

        [$settlement, $receivable] = $this->createSettlementFixture();

        $adjustment = SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'sale_id' => $receivable->sale_id,
            'type' => 'refund_organizer_deduction',
            'amount' => 30,
            'reason' => 'Reembolso após repasse.',
            'status' => 'pending_recovery',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/finance/adjustments/{$adjustment->uuid}/resolve-recovery", [
                'resolution_type' => 'recovered_from_organizer',
                'resolution_notes' => 'Cobrança compensada no próximo ciclo.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'recovered')
            ->assertJsonPath('data.resolution_type', 'recovered_from_organizer');

        $adjustment->refresh();

        $this->assertSame('recovered', $adjustment->status);
        $this->assertNotNull($adjustment->resolved_at);
        $this->assertDatabaseHas('ledger_entries', [
            'settlement_adjustment_id' => $adjustment->id,
            'entry_type' => 'recovery_collected',
            'direction' => 'credit',
            'amount' => 30,
        ]);
    }

    #[Test]
    public function resolve_pending_review_endpoint_can_reclassify_to_recovery(): void
    {
        $this->grantPermission('finance', 'update');

        [$settlement, $receivable] = $this->createSettlementFixture();

        $adjustment = SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'sale_id' => $receivable->sale_id,
            'type' => 'chargeback_platform_exposure',
            'amount' => 40,
            'reason' => 'Contestação do PSP.',
            'status' => 'pending_review',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/v1/finance/adjustments/{$adjustment->uuid}/resolve-review", [
                'resolution_type' => 'reclassify_to_recovery',
                'resolution_notes' => 'Financeiro concluiu que deve ser cobrado do organizador.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'reclassified')
            ->assertJsonPath('data.resolution_type', 'reclassify_to_recovery');

        $adjustment->refresh();

        $this->assertSame('reclassified', $adjustment->status);
        $this->assertDatabaseHas('settlement_adjustments', [
            'tenant_id' => $this->tenant->id,
            'type' => 'manual_recovery_after_review',
            'status' => 'pending_recovery',
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'review_reclassified_to_recovery',
            'amount' => 40,
        ]);
    }

    #[Test]
    public function reconciliation_summary_includes_open_adjustments_and_integrity_counters(): void
    {
        $this->grantPermission('finance', 'read');

        [$settlement, $receivable] = $this->createSettlementFixture();

        SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'sale_id' => $receivable->sale_id,
            'type' => 'refund_platform_exposure',
            'amount' => 12.5,
            'reason' => 'Exposição aguardando decisão.',
            'status' => 'pending_review',
        ]);

        $orphanReceivable = $receivable->replicate();
        $orphanReceivable->uuid = (string) Str::uuid();
        $orphanReceivable->sale_id = $this->createStandaloneSaleId();
        $orphanReceivable->settlement_id = null;
        $orphanReceivable->status = 'scheduled';
        $orphanReceivable->save();

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/finance/reconciliation/summary')
            ->assertStatus(200)
            ->assertJsonPath('data.open_adjustments_amount', 12.5)
            ->assertJsonPath('data.integrity.receivables_without_settlement', 1)
            ->assertJsonPath('data.integrity.open_adjustments', 1);
    }

    private function createSettlementFixture(): array
    {
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-ADJ-'.Str::upper(Str::random(4)),
            'total_amount' => 100,
            'paid_amount' => 100,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-ADJ-'.Str::upper(Str::random(6)),
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
        ]);

        $receivable = Receivable::create([
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'event_id' => $product->event_id,
            'settlement_id' => $settlement->id,
            'status' => 'awaiting_release',
            'currency' => 'BRL',
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => now()->subHour(),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => 'SPLI_'.Str::upper(Str::random(8)),
        ]);

        return [$settlement, $receivable];
    }

    private function createStandaloneSaleId(): int
    {
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-ORPH-'.Str::upper(Str::random(4)),
            'total_amount' => 50,
            'paid_amount' => 50,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        return $sale->id;
    }
}
