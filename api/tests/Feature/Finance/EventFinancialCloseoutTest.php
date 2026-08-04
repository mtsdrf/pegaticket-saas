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

class EventFinancialCloseoutTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('event-finance-closeout@test.com');
    }

    #[Test]
    public function event_closeout_endpoint_returns_financial_summary_for_the_event(): void
    {
        $this->grantPermission('finance', 'read');

        [$event, $receivable, $settlement] = $this->createEventFinancialFixture(released: true);

        SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'sale_id' => $receivable->sale_id,
            'type' => 'refund_platform_exposure',
            'amount' => 12.50,
            'reason' => 'Exposição aguardando decisão do financeiro.',
            'status' => 'pending_review',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/events/{$event->uuid}/finance/closeout")
            ->assertStatus(200)
            ->assertJsonPath('data.event.uuid', $event->uuid)
            ->assertJsonPath('data.closeout_status', 'settled_with_exceptions')
            ->assertJsonPath('data.totals.organizer_net_amount', 95)
            ->assertJsonPath('data.totals.released_amount', 95)
            ->assertJsonPath('data.totals.pending_review_amount', 12.5)
            ->assertJsonPath('data.adjustments.open_count', 1);
    }

    #[Test]
    public function event_bordereau_export_downloads_a_csv_for_the_event(): void
    {
        $this->grantPermission('finance', 'read');

        [$event, $receivable, $settlement] = $this->createEventFinancialFixture(released: false);

        SettlementAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'settlement_id' => $settlement->id,
            'receivable_id' => $receivable->id,
            'sale_id' => $receivable->sale_id,
            'type' => 'manual_debit',
            'amount' => 5,
            'reason' => 'Correção operacional.',
            'status' => 'applied',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->get("/api/v1/events/{$event->uuid}/finance/bordereau");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('event_uuid,event_name,sale_uuid,sale_codigo', $content);
        $this->assertStringContainsString($event->uuid, $content);
        $this->assertStringContainsString($receivable->uuid, $content);
        $this->assertStringContainsString($settlement->code, $content);
    }

    #[Test]
    public function event_closeout_of_another_tenant_returns_404(): void
    {
        $this->grantPermission('finance', 'read');

        [$event] = $this->createEventFinancialFixture(released: true);

        $this->setUpTenantScopedUser('event-finance-other-tenant@test.com');
        $this->grantPermission('finance', 'read');

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/v1/events/{$event->uuid}/finance/closeout")
            ->assertStatus(404);
    }

    private function createEventFinancialFixture(bool $released): array
    {
        $product = $this->createProduct($this->tenant->id, [
            'price' => 100,
        ]);
        $product->event->update([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-EVT-'.Str::upper(Str::random(4)),
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
            'sale_id' => $sale->id,
            'ticket_type_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-EVT-'.Str::upper(Str::random(6)),
            'status' => $released ? 'released' : 'release_requested',
            'scheduled_for' => now()->subHours(12),
            'released_at' => $released ? now()->subHours(2) : null,
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
            'status' => $released ? 'released' : 'release_requested',
            'currency' => 'BRL',
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 0,
            'net_amount' => 95,
            'settlement_reference' => 'event_end_d_plus_1',
            'event_ends_at' => now()->subDay(),
            'available_at' => now()->subHours(18),
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_'.Str::upper(Str::random(8)),
            'provider_split_id' => 'SPLI_'.Str::upper(Str::random(8)),
        ]);

        return [$product->event->fresh(), $receivable, $settlement];
    }
}
