<?php

namespace Tests\Feature\Finance;

use App\Events\Sale\SalePaid;
use App\Models\Finance\PlatformFinanceSettings;
use App\Models\Finance\Receivable;
use App\Models\Finance\SettlementAdjustment;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Services\Finance\RiskReserveReleaseService;
use App\Services\Finance\SettlementGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class RiskReserveTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('risk-reserve@test.com');
    }

    private function paySale(string $codigo, float $amount): Sale
    {
        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => $codigo,
            'total_amount' => $amount,
            'paid_amount' => $amount,
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
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        return $sale->fresh();
    }

    #[Test]
    public function sale_paid_withholds_a_risk_reserve_when_enabled(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => true,
            'extra_reserve_percentage' => 10,
            'extra_reserve_release_offset_days' => 30,
        ]);

        $sale = $this->paySale('SALE-RR-1', 200.0);

        $receivable = Receivable::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('20.00', $receivable->reserve_amount);
        $this->assertSame('180.00', $receivable->net_amount);
        $this->assertSame('held', $receivable->reserve_status);
        $this->assertNotNull($receivable->reserve_release_at);
        $this->assertTrue(
            $receivable->reserve_release_at->equalTo($receivable->available_at->copy()->addDays(30))
        );

        $this->assertDatabaseHas('ledger_entries', [
            'receivable_id' => $receivable->id,
            'entry_type' => 'risk_reserve_hold',
            'direction' => 'debit',
            'amount' => '20.00',
        ]);
    }

    #[Test]
    public function sale_paid_does_not_withhold_reserve_when_disabled(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
            'extra_reserve_percentage' => 10,
            'extra_reserve_release_offset_days' => 30,
        ]);

        $sale = $this->paySale('SALE-RR-2', 150.0);

        $receivable = Receivable::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame('0.00', $receivable->reserve_amount);
        $this->assertSame('150.00', $receivable->net_amount);
        $this->assertSame('none', $receivable->reserve_status);
        $this->assertNull($receivable->reserve_release_at);
    }

    #[Test]
    public function risk_reserve_release_service_credits_the_held_reserve_back_to_the_organizer(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => true,
            'extra_reserve_percentage' => 10,
            'extra_reserve_release_offset_days' => 30,
        ]);

        $sale = $this->paySale('SALE-RR-3', 300.0);
        $receivable = Receivable::query()->where('sale_id', $sale->id)->firstOrFail();

        (new SettlementGenerationService)->generateAvailable(
            cutoffAt: $receivable->available_at->copy()->addDay(),
            tenantId: $this->tenant->id,
        );

        $result = (new RiskReserveReleaseService)->releaseDue(
            cutoffAt: $receivable->reserve_release_at->copy()->addDay(),
            tenantId: $this->tenant->id,
        );

        $this->assertSame(1, $result['reserves_seen']);
        $this->assertSame(1, $result['released']);

        $receivable->refresh();
        $this->assertSame('released', $receivable->reserve_status);
        $this->assertNotNull($receivable->reserve_released_at);

        $adjustment = SettlementAdjustment::query()->where('receivable_id', $receivable->id)->firstOrFail();
        $this->assertSame('reserve_release', $adjustment->type);
        $this->assertSame('applied', $adjustment->status);
        $this->assertSame('30.00', $adjustment->amount);

        $this->assertDatabaseHas('ledger_entries', [
            'receivable_id' => $receivable->id,
            'entry_type' => 'risk_reserve_release',
            'direction' => 'credit',
            'amount' => '30.00',
        ]);
    }

    #[Test]
    public function risk_reserve_release_service_is_idempotent(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => true,
            'extra_reserve_percentage' => 10,
            'extra_reserve_release_offset_days' => 30,
        ]);

        $sale = $this->paySale('SALE-RR-4', 100.0);
        $receivable = Receivable::query()->where('sale_id', $sale->id)->firstOrFail();

        $service = new RiskReserveReleaseService;
        $cutoff = $receivable->reserve_release_at->copy()->addDay();

        $service->releaseDue(cutoffAt: $cutoff, tenantId: $this->tenant->id);
        $result = $service->releaseDue(cutoffAt: $cutoff, tenantId: $this->tenant->id);

        $this->assertSame(0, $result['reserves_seen']);
        $this->assertDatabaseCount('settlement_adjustments', 1);
    }
}
