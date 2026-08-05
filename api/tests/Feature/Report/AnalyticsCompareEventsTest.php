<?php

namespace Tests\Feature\Report;

use App\Models\Event\Event;
use App\Models\Event\TicketType;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Comparação entre eventos (roadmap Fase A2, último item do relatório 1
 * tela 2, docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md):
 * curva de vendas indexada por "dias desde abertura de vendas" de cada
 * evento (não data de calendário), ver
 * App\Services\Report\AnalyticsService::compareEvents().
 */
class AnalyticsCompareEventsTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-compare-events@test.com');
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

    private function makeSaleItem(Sale $sale, TicketType $ticketType, float $unitPrice, int $quantity = 1): SaleItem
    {
        return SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
        ]);
    }

    #[Test]
    public function compares_two_events_aligning_by_days_since_sales_opened_not_calendar_date(): void
    {
        // Evento A: vendas abriram há 10 dias; venda ocorreu no dia 3 desde a abertura.
        $ticketTypeA = $this->createProduct($this->tenant->id, [
            'sales_start_at' => now()->subDays(10),
        ]);
        $eventA = Event::find($ticketTypeA->event_id);

        $saleA = $this->makeSale(['total_amount' => 150, 'created_at' => now()->subDays(7)]);
        $saleA->created_at = now()->subDays(7);
        $saleA->save();
        $this->makeSaleItem($saleA, $ticketTypeA, 150);

        // Evento B: vendas abriram há 30 dias (data de calendário bem diferente),
        // mas a venda também ocorreu no dia 3 desde a abertura dele.
        $ticketTypeB = $this->createProduct($this->tenant->id, [
            'sales_start_at' => now()->subDays(30),
        ]);
        $eventB = Event::find($ticketTypeB->event_id);

        $saleB = $this->makeSale(['total_amount' => 200, 'created_at' => now()->subDays(27)]);
        $saleB->created_at = now()->subDays(27);
        $saleB->save();
        $this->makeSaleItem($saleB, $ticketTypeB, 200);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/compare-events?event_uuids[]='.$eventA->uuid.'&event_uuids[]='.$eventB->uuid);

        $response->assertStatus(200);

        $events = $response->json('data.events');
        $this->assertCount(2, $events);

        $eventAData = collect($events)->firstWhere('event_uuid', $eventA->uuid);
        $eventBData = collect($events)->firstWhere('event_uuid', $eventB->uuid);

        // Ambos vendem no "dia 3 desde abertura", mesmo com datas de calendário diferentes.
        $this->assertSame(3, $eventAData['series'][0]['day']);
        $this->assertSame(3, $eventBData['series'][0]['day']);

        $this->assertSame('150.00', $eventAData['totals']['total_revenue']);
        $this->assertSame('200.00', $eventBData['totals']['total_revenue']);
        $this->assertSame(1, $eventAData['totals']['total_orders']);
        $this->assertSame(1, $eventBData['totals']['total_orders']);
    }

    #[Test]
    public function returns_not_found_when_one_event_belongs_to_another_tenant(): void
    {
        $ticketTypeA = $this->createProduct($this->tenant->id);
        $eventA = Event::find($ticketTypeA->event_id);

        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Outro Tenant',
            'slug' => 'outro-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $ticketTypeOther = $this->createProduct($otherTenant->id);
        $eventOther = Event::find($ticketTypeOther->event_id);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/compare-events?event_uuids[]='.$eventA->uuid.'&event_uuids[]='.$eventOther->uuid);

        $response->assertStatus(404);
    }

    #[Test]
    public function respects_maximum_number_of_events(): void
    {
        $uuids = [];
        for ($i = 0; $i < 6; $i++) {
            $ticketType = $this->createProduct($this->tenant->id);
            $uuids[] = Event::find($ticketType->event_id)->uuid;
        }

        $query = collect($uuids)->map(fn ($uuid) => 'event_uuids[]='.$uuid)->implode('&');

        $response = $this->auth()->getJson('/api/v1/reports/analytics/compare-events?'.$query);

        $response->assertStatus(422);
    }
}
