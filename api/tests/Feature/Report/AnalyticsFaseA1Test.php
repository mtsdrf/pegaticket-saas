<?php

namespace Tests\Feature\Report;

use App\Models\Finance\Receivable;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Ticket\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Roadmap Fase A1 (docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md):
 * KPIs de ocupação/receita líquida, vendas por dimensão unificada,
 * relatório de pagamentos e alertas básicos (estoque baixo/pagamento).
 */
class AnalyticsFaseA1Test extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-fase-a1@test.com');
        $this->grantPermission('dashboard', 'read');
        $this->grantPermission('analytics', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function issueTicket(int $saleId, int $ticketTypeId): Ticket
    {
        $saleItem = SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleId,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        return Ticket::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_item_id' => $saleItem->id,
            'ticket_type_id' => $ticketTypeId,
            'status' => 'ativo',
        ]);
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
    public function indicators_expose_occupancy_and_net_revenue(): void
    {
        $ticketType = $this->createProduct($this->tenant->id, ['quantity_available' => 10]);
        $unlimitedType = $this->createProduct($this->tenant->id, ['quantity_available' => null]);

        $sale = $this->makeSale();
        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->issueTicket($sale->id, $ticketType->id);

        Receivable::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'status' => 'scheduled',
            'currency' => 'BRL',
            'gross_amount' => 100,
            'platform_fee_amount' => 5,
            'processor_fee_amount' => 2,
            'net_amount' => 93,
            'reserve_amount' => 0,
            'reserve_status' => 'none',
            'available_at' => now()->addDays(1),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/indicators');

        $response->assertStatus(200)
            ->assertJsonPath('data.tickets_issued', 1)
            ->assertJsonPath('data.commercial_capacity', 10)
            ->assertJsonPath('data.occupancy_percentage', 10)
            ->assertJsonPath('data.net_revenue_amount', '93.00');

        $this->assertNotNull($unlimitedType);
    }

    #[Test]
    public function sales_by_dimension_supports_ticket_type_client_and_origin(): void
    {
        $ticketType = $this->createProduct($this->tenant->id, ['name' => 'Pista']);
        $sale = $this->makeSale(['origin' => 'staff']);
        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50,
            'line_total' => 100,
        ]);

        $byTicketType = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-dimension?dimension=ticket_type')
            ->assertStatus(200)
            ->assertJsonPath('data.dimension', 'ticket_type')
            ->json('data.items');
        $this->assertSame('Pista', $byTicketType[0]['label']);
        $this->assertEquals(2.0, $byTicketType[0]['quantity_sold']);

        $byClient = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-dimension?dimension=client')
            ->assertStatus(200)
            ->assertJsonPath('data.dimension', 'client')
            ->json('data.items');
        $this->assertSame(1, $byClient[0]['order_count']);

        $byOrigin = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-dimension?dimension=origin')
            ->assertStatus(200)
            ->assertJsonPath('data.dimension', 'origin')
            ->json('data.items');
        $this->assertSame('staff', $byOrigin[0]['key']);
    }

    #[Test]
    public function payments_summary_computes_approval_and_rejection_rates(): void
    {
        $this->makeSale(['status' => 'confirmed', 'total_amount' => 100]);
        $this->makeSale(['status' => 'confirmed', 'total_amount' => 100]);
        $this->makeSale(['status' => 'confirmed', 'total_amount' => 100]);
        $this->makeSale(['status' => 'rejected', 'is_paid' => false, 'paid_at' => null, 'total_amount' => 50]);
        $this->makeSale(['status' => 'pending_approval', 'is_paid' => false, 'paid_at' => null, 'total_amount' => 70]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/payments');

        $response->assertStatus(200)
            ->assertJsonPath('data.confirmed.count', 3)
            ->assertJsonPath('data.rejected.count', 1)
            ->assertJsonPath('data.pending_approval.count', 1)
            ->assertJsonPath('data.approval_rate_percentage', 75)
            ->assertJsonPath('data.rejection_rate_percentage', 25);
    }

    #[Test]
    public function alerts_report_low_stock_and_pending_approval_queue(): void
    {
        $ticketType = $this->createProduct($this->tenant->id, ['name' => 'Camarote', 'quantity_available' => 10]);

        $stockSale = $this->makeSale();
        for ($i = 0; $i < 9; $i++) {
            $this->issueTicket($stockSale->id, $ticketType->id);
        }

        for ($i = 0; $i < 15; $i++) {
            $this->makeSale(['status' => 'pending_approval', 'is_paid' => false, 'paid_at' => null]);
        }

        $response = $this->auth()->getJson('/api/v1/reports/alerts');

        $response->assertStatus(200);
        $alerts = $response->json('data');

        $lowStock = collect($alerts)->firstWhere('type', 'low_stock');
        $this->assertNotNull($lowStock);
        $this->assertSame('Camarote', $lowStock['meta']['ticket_type_name']);
        $this->assertSame(1, $lowStock['meta']['remaining']);

        $pendingQueue = collect($alerts)->firstWhere('type', 'payment_pending_queue');
        $this->assertNotNull($pendingQueue);
        $this->assertSame(15, $pendingQueue['meta']['pending_approval_count']);
    }
}
