<?php

namespace Tests\Feature\Finance;

use App\Events\Sale\SalePaid;
use App\Models\Finance\LedgerEntry;
use App\Models\Finance\PlatformFinanceSettings;
use App\Models\Finance\Receivable;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Subscription\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

class ReceivableGenerationTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('receivables@test.com');
    }

    #[Test]
    public function sale_paid_generates_a_receivable_and_ledger_entries(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 5,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
        ]);

        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-1000',
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
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

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORD_TEST_1',
            'method' => 'pix',
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => [
                'split_id' => 'SPLI_TEST_1',
                'split_release_scheduled' => $product->event->ends_at->copy()->addDay()->startOfDay()->toIso8601String(),
            ],
        ]);

        event(new SalePaid($sale->uuid, $this->userId));

        $receivable = Receivable::query()->where('sale_id', $sale->id)->firstOrFail();

        $this->assertSame($this->tenant->id, $receivable->tenant_id);
        $this->assertSame($sale->id, $receivable->sale_id);
        $this->assertSame($payment->id, $receivable->payment_id);
        $this->assertSame('100.00', $receivable->gross_amount);
        $this->assertSame('5.00', $receivable->platform_fee_amount);
        $this->assertSame('95.00', $receivable->net_amount);
        $this->assertSame($product->event_id, $receivable->event_id);
        $this->assertSame('SPLI_TEST_1', $receivable->provider_split_id);
        $this->assertTrue($receivable->available_at->equalTo($product->event->ends_at->copy()->addDay()->startOfDay()));
        $this->assertSame(
            $product->event->ends_at->copy()->addDay()->startOfDay()->toIso8601String(),
            $receivable->metadata['pagbank_custody_release_scheduled']
        );

        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(
            ['platform_fee', 'receivable_gross'],
            LedgerEntry::query()->orderBy('entry_type')->pluck('entry_type')->all()
        );
    }

    #[Test]
    public function sale_paid_is_idempotent_for_receivable_generation(): void
    {
        PlatformFinanceSettings::create([
            'platform_fee_fixed_amount' => 0,
            'default_settlement_offset_days' => 1,
            'settlement_reference' => 'event_end',
            'split_custody_enabled' => true,
            'extra_reserve_enabled' => false,
        ]);

        $product = $this->createProduct($this->tenant->id);
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-1001',
            'total_amount' => 40.0,
            'paid_amount' => 40.0,
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
            'unit_price' => 40,
            'line_total' => 40,
        ]);

        event(new SalePaid($sale->uuid, $this->userId));
        event(new SalePaid($sale->uuid, $this->userId));

        $this->assertDatabaseCount('receivables', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
    }
}
