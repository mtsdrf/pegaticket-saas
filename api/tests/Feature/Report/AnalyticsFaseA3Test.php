<?php

namespace Tests\Feature\Report;

use App\Models\AuditLog;
use App\Models\CashSession\CashSession;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use App\Models\Ticket\TicketResaleListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Roadmap Fase A3, parte 1 (docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md):
 * relatório de antifraude, revenda/transferência e bilheteria por
 * operador/terminal. Os 8 segmentos nomeados de RFM têm cobertura própria
 * em tests/Unit/Services/Report/RfmCalculatorTest.php.
 */
class AnalyticsFaseA3Test extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-fase-a3@test.com');
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
    public function risk_report_computes_totals_rate_and_heuristic_breakdown(): void
    {
        $this->makeSale([
            'total_amount' => 250,
            'risk_flagged' => true,
            'risk_reason' => 'Este cliente fez 3 compras pagas para o mesmo evento nas últimas 6 horas (possível revenda/scalping automatizado). Apenas um alerta — revise manualmente antes de agir.',
        ]);

        $this->makeSale([
            'total_amount' => 150,
            'risk_flagged' => true,
            'risk_reason' => '4 clientes diferentes concluíram uma compra paga a partir do mesmo IP nos últimos 30 minutos (possível bot/fraude comprando em massa disfarçado de vários clientes). Apenas um alerta — revise manualmente antes de agir.',
        ]);

        $this->makeSale(['total_amount' => 100]);
        $this->makeSale(['total_amount' => 100]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/risk');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.flagged_count', 2)
            ->assertJsonPath('data.totals.flagged_amount', '400.00')
            ->assertJsonPath('data.totals.paid_sales_count', 4)
            ->assertJsonPath('data.totals.flagged_rate_percentage', 50)
            ->assertJsonCount(2, 'data.flagged_sales');

        $byHeuristic = collect($response->json('data.by_heuristic'))->keyBy('heuristic');
        $this->assertSame(1, $byHeuristic['compras_repetidas']['count'] ?? null);
        $this->assertSame(1, $byHeuristic['velocity_ip']['count'] ?? null);
    }

    #[Test]
    public function risk_report_never_leaks_flagged_sales_from_another_tenant(): void
    {
        $this->makeSale(['risk_flagged' => true, 'risk_reason' => 'x']);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $client = $this->createClient($otherTenant->id);
        Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-'.Str::random(8),
            'total_amount' => 999,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'staff',
            'risk_flagged' => true,
            'risk_reason' => 'x',
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/risk');

        $response->assertStatus(200)->assertJsonPath('data.totals.flagged_count', 1);
    }

    #[Test]
    public function resale_report_computes_volume_payout_status_and_transfer_split(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);

        $sale = $this->makeSale();
        $saleItem = SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_item_id' => $saleItem->id,
            'ticket_type_id' => $ticketType->id,
            'status' => 'ativo',
        ]);

        $seller = $this->createClient($this->tenant->id);
        $buyer = $this->createClient($this->tenant->id);

        $listing = TicketResaleListing::create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $ticket->id,
            'seller_final_customer_id' => $seller->id,
            'buyer_final_customer_id' => $buyer->id,
            'original_unit_price' => 100,
            'asking_price' => 120,
            'status' => 'vendido',
            'seller_payout_amount' => 110,
            'seller_payout_status' => 'pendente_liberacao',
            'sold_at' => now(),
        ]);

        // Evento de transferência gerado pelo fechamento da revenda acima.
        AuditLog::record(event: 'ticket_transferred', meta: ['ticket_uuid' => $ticket->uuid], actorId: null);

        // Transferência simples (sem revenda) — outro ticket, sem listing.
        $ticket2 = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_item_id' => $saleItem->id,
            'ticket_type_id' => $ticketType->id,
            'status' => 'ativo',
        ]);
        AuditLog::record(event: 'ticket_transferred', meta: ['ticket_uuid' => $ticket2->uuid], actorId: null);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/resale');

        $response->assertStatus(200)
            ->assertJsonPath('data.resale.sold_count', 1)
            ->assertJsonPath('data.resale.total_transacted_amount', '120.00')
            ->assertJsonPath('data.payout_by_status.pendente_liberacao.count', 1)
            ->assertJsonPath('data.transfers.total_count', 2)
            ->assertJsonPath('data.transfers.resale_count', 1)
            ->assertJsonPath('data.transfers.simple_count', 1);

        $this->assertNotNull($listing->fresh());
    }

    #[Test]
    public function resale_report_transfer_count_never_leaks_another_tenant_ticket(): void
    {
        AuditLog::record(event: 'ticket_transferred', meta: ['ticket_uuid' => (string) Str::uuid()], actorId: null);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/resale');

        $response->assertStatus(200)->assertJsonPath('data.transfers.total_count', 0);
    }

    #[Test]
    public function operator_report_aggregates_checkins_sales_and_cash_sessions_by_operator(): void
    {
        $ticketType = $this->createProduct($this->tenant->id);

        $sale = $this->makeSale();
        $saleItem = SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $ticket = Ticket::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_item_id' => $saleItem->id,
            'ticket_type_id' => $ticketType->id,
            'status' => 'utilizado',
        ]);

        TicketCheckin::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $ticket->id,
            'gate_name' => 'Portão A',
            'operator_id' => $this->userId,
            'checked_in_at' => now(),
            'result' => 'valido',
            'access_type' => 'entrada',
        ]);

        $this->makeSale(['origin' => 'staff', 'created_by' => $this->userId, 'total_amount' => 300]);

        CashSession::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'opened_by' => $this->userId,
            'opening_amount' => 100,
            'closing_amount' => 100,
            'difference_amount' => 0,
            'status' => 'fechado',
            'opened_at' => now(),
            'closed_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/operators');

        $response->assertStatus(200)
            ->assertJsonPath('data.checkins_by_operator.0.total_reads', 1)
            ->assertJsonPath('data.checkins_by_operator.0.granted_reads', 1)
            ->assertJsonPath('data.checkins_by_gate.0.gate_name', 'Portão A')
            ->assertJsonPath('data.cash_sessions_by_operator.0.sessions_count', 1);

        $salesByOperator = collect($response->json('data.sales_by_operator'));
        $this->assertGreaterThanOrEqual(1, $salesByOperator->sum('sales_count'));
    }

    #[Test]
    public function top_clients_endpoint_exposes_additive_rfm_segment8_field(): void
    {
        $this->makeSale(['total_amount' => 500]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/top-clients');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.0.rfm_segment8'));
        $this->assertNotEmpty($response->json('data.0.rfm_segment8_label'));
        $this->assertNotEmpty($response->json('data.0.rfm_label'));
    }
}
