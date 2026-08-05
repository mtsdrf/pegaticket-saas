<?php

namespace Tests\Feature\Report;

use App\Models\Affiliate\Affiliate;
use App\Models\Event\Event;
use App\Models\Inventory\InventoryHold;
use App\Models\Inventory\InventoryHoldItem;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Storefront\Coupon;
use App\Models\Storefront\CouponRedemption;
use App\Models\Ticket\Ticket;
use App\Models\Venue\Seat;
use App\Models\Venue\Venue;
use App\Models\Venue\VenueMapVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Roadmap Fase A2, parte 1 (docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md):
 * relatório de afiliados consolidado, cupons consolidado, reembolsos/
 * chargebacks dedicado e inventário/assentos avançado.
 */
class AnalyticsFaseA2Test extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-fase-a2@test.com');
        $this->grantPermission('analytics', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function makeSale(array $overrides = []): Sale
    {
        $client = $this->createClient($this->tenant->id);

        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-'.Str::random(8),
            'total_amount' => 100.0,
            'paid_amount' => 100.0,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ], $overrides));
    }

    #[Test]
    public function affiliates_report_ranks_by_revenue_and_computes_roi(): void
    {
        $affiliate = Affiliate::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Promotor A',
            'tracking_code' => 'PROMOA',
            'commission_percentage' => 10,
            'status' => 'active',
        ]);

        $sale = $this->makeSale(['total_amount' => 200]);

        \DB::table('affiliate_commissions')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $affiliate->id,
            'sale_id' => $sale->id,
            'sale_amount' => 200,
            'percentage_applied' => 10,
            'amount' => 20,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/affiliates');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.affiliates_count', 1)
            ->assertJsonPath('data.items.0.affiliate_name', 'Promotor A')
            ->assertJsonPath('data.items.0.attributed_sales_count', 1)
            ->assertJsonPath('data.items.0.attributed_revenue', '200.00')
            ->assertJsonPath('data.items.0.commission_paid_amount', '20.00')
            ->assertJsonPath('data.items.0.roi_percentage', 900);
    }

    #[Test]
    public function coupons_report_flags_abuse_signal_for_concentrated_usage(): void
    {
        $coupon = Coupon::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'PROMO10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $client = $this->createClient($this->tenant->id);

        for ($i = 0; $i < 6; $i++) {
            $sale = $this->makeSale(['final_customer_id' => $client->id, 'total_amount' => 90, 'discount_amount' => 10]);

            CouponRedemption::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'coupon_id' => $coupon->id,
                'final_customer_id' => $client->id,
                'sale_id' => $sale->id,
                'redeemed_at' => now(),
            ]);
        }

        $response = $this->auth()->getJson('/api/v1/reports/analytics/coupons');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.coupon_code', 'PROMO10')
            ->assertJsonPath('data.items.0.usage_count', 6)
            ->assertJsonPath('data.items.0.distinct_customers_count', 1)
            ->assertJsonPath('data.items.0.abuse_signal', true)
            ->assertJsonPath('data.totals.coupons_with_abuse_signal_count', 1);
    }

    #[Test]
    public function refunds_report_computes_totals_and_refund_rate(): void
    {
        $sale = $this->makeSale(['total_amount' => 300]);
        $this->makeSale(['total_amount' => 100]);

        \DB::table('sale_refunds')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'type' => 'total',
            'amount' => 300,
            'reason' => 'Cliente desistiu do evento',
            'refunded_at' => now(),
            'status' => 'registrado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/refunds');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.refunds_count', 1)
            ->assertJsonPath('data.totals.total_refunded_amount', '300.00')
            ->assertJsonPath('data.totals.refund_rate_percentage', 50)
            ->assertJsonPath('data.by_type.total.count', 1)
            ->assertJsonPath('data.top_reasons.0.reason', 'Cliente desistiu do evento');
    }

    #[Test]
    public function inventory_report_requires_event_uuid_and_computes_sector_occupancy(): void
    {
        $this->auth()->getJson('/api/v1/reports/analytics/inventory')->assertStatus(422);

        $venue = Venue::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Arena Central',
        ]);

        $mapVersion = VenueMapVersion::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'venue_id' => $venue->id,
            'version_number' => 1,
            'is_published' => true,
        ]);

        $ticketType = $this->createProduct($this->tenant->id);
        $event = Event::find($ticketType->event_id);
        $event->venue_map_version_id = $mapVersion->id;
        $event->save();

        $pistaSeat1 = Seat::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'venue_map_version_id' => $mapVersion->id,
            'sector_name' => 'Pista',
            'label' => 'P1',
            'kind' => 'assento',
            'capacity' => 1,
            'status' => 'disponivel',
        ]);

        Seat::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'venue_map_version_id' => $mapVersion->id,
            'sector_name' => 'Pista',
            'label' => 'P2',
            'kind' => 'assento',
            'capacity' => 1,
            'status' => 'disponivel',
        ]);

        $sale = $this->makeSale();
        $saleItem = SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'seat_id' => $pistaSeat1->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        Ticket::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_item_id' => $saleItem->id,
            'ticket_type_id' => $ticketType->id,
            'seat_id' => $pistaSeat1->id,
            'status' => 'ativo',
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/inventory?event_uuid='.$event->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_seat_map', true)
            ->assertJsonPath('data.totals.total_seats', 2)
            ->assertJsonPath('data.totals.sold_seats', 1)
            ->assertJsonPath('data.totals.occupancy_percentage', 50)
            ->assertJsonPath('data.sectors.0.sector_name', 'Pista')
            ->assertJsonPath('data.sectors.0.sold_seats', 1)
            ->assertJsonPath('data.sectors.0.available_seats', 1);

        $this->assertNotNull(InventoryHold::class);
        $this->assertNotNull(InventoryHoldItem::class);
    }
}
