<?php

namespace Tests\Feature\Report;

use App\Models\Event\TicketType;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Roadmap Fase A3, parte 2 (docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md,
 * seção 5.3): coortes de retenção, LTV histórico e afinidade entre eventos.
 * Tempo real com filtro obrigatório — sem tabela de snapshot (decisão do
 * usuário registrada no roadmap).
 */
class AnalyticsFaseA3Part2Test extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-fase-a3-part2@test.com');
        $this->grantPermission('analytics', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function makeSale(int $clientId, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $clientId,
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
    public function cohorts_endpoint_requires_from_month(): void
    {
        $response = $this->auth()->getJson('/api/v1/reports/analytics/cohorts');

        $response->assertStatus(422)->assertJsonValidationErrors('from');
    }

    #[Test]
    public function cohorts_report_groups_first_paid_purchase_and_measures_retention(): void
    {
        $loyalClient = $this->createClient($this->tenant->id);
        $oneTimeClient = $this->createClient($this->tenant->id);

        $cohortMonth = now()->startOfMonth()->subMonths(2);

        $this->makeSale($loyalClient->id, ['paid_at' => $cohortMonth->copy()->addDays(2), 'created_at' => $cohortMonth->copy()->addDays(2)]);
        $this->makeSale($loyalClient->id, ['paid_at' => $cohortMonth->copy()->addMonths(1)->addDays(2), 'created_at' => $cohortMonth->copy()->addMonths(1)->addDays(2)]);

        $this->makeSale($oneTimeClient->id, ['paid_at' => $cohortMonth->copy()->addDays(5), 'created_at' => $cohortMonth->copy()->addDays(5)]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/cohorts?from='.$cohortMonth->toDateString());

        $response->assertStatus(200);

        $cohorts = collect($response->json('data.cohorts'));
        $row = $cohorts->firstWhere('cohort_month', $cohortMonth->format('Y-m'));

        $this->assertNotNull($row);
        $this->assertSame(2, $row['cohort_size']);
        $this->assertSame(100, $row['retention'][0]['retention_percentage']);
        $this->assertSame(50, $row['retention'][1]['retention_percentage']);
    }

    #[Test]
    public function cohorts_report_never_leaks_first_purchase_before_requested_from(): void
    {
        $client = $this->createClient($this->tenant->id);
        $oldMonth = now()->startOfMonth()->subMonths(10);
        $this->makeSale($client->id, ['paid_at' => $oldMonth->copy()->addDays(1), 'created_at' => $oldMonth->copy()->addDays(1)]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/cohorts?from='.now()->startOfMonth()->toDateString());

        $response->assertStatus(200)->assertJsonPath('data.cohorts', []);
    }

    #[Test]
    public function ltv_report_groups_by_segment_by_default(): void
    {
        $client = $this->createClient($this->tenant->id);
        $this->makeSale($client->id, ['total_amount' => 500]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/ltv');

        $response->assertStatus(200)->assertJsonPath('data.group_by', 'segment');
        $this->assertSame(1, $response->json('data.overall.customers_count'));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.groups')));
    }

    #[Test]
    public function ltv_report_groups_by_cohort_when_requested(): void
    {
        $client = $this->createClient($this->tenant->id);
        $month = now()->startOfMonth()->subMonths(1);
        $this->makeSale($client->id, ['total_amount' => 300, 'paid_at' => $month->copy()->addDays(1), 'created_at' => $month->copy()->addDays(1)]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/ltv?group_by=cohort');

        $response->assertStatus(200)->assertJsonPath('data.group_by', 'cohort');
        $groups = collect($response->json('data.groups'));
        $row = $groups->firstWhere('key', $month->format('Y-m'));
        $this->assertNotNull($row);
        $this->assertSame('300.00', $row['total_ltv']);
    }

    #[Test]
    public function event_affinity_endpoint_requires_event_uuid(): void
    {
        $response = $this->auth()->getJson('/api/v1/reports/analytics/event-affinity');

        $response->assertStatus(422)->assertJsonValidationErrors('event_uuid');
    }

    #[Test]
    public function event_affinity_report_lists_events_bought_by_the_same_customers(): void
    {
        $eventA = $this->createProduct($this->tenant->id);
        $eventB = $this->createProduct($this->tenant->id);
        $eventC = $this->createProduct($this->tenant->id);

        $sharedClient1 = $this->createClient($this->tenant->id);
        $sharedClient2 = $this->createClient($this->tenant->id);
        $onlyAClient = $this->createClient($this->tenant->id);

        $this->attachTicketTypeToSale($eventA, $sharedClient1->id);
        $this->attachTicketTypeToSale($eventB, $sharedClient1->id);

        $this->attachTicketTypeToSale($eventA, $sharedClient2->id);
        $this->attachTicketTypeToSale($eventC, $sharedClient2->id);

        $this->attachTicketTypeToSale($eventA, $onlyAClient->id);

        $eventAUuid = $eventA->event->uuid;

        $response = $this->auth()->getJson('/api/v1/reports/analytics/event-affinity?event_uuid='.$eventAUuid);

        $response->assertStatus(200)->assertJsonPath('data.base_customers_count', 3);

        $affinities = collect($response->json('data.affinities'))->keyBy('event_uuid');
        $this->assertSame(1, $affinities[$eventB->event->uuid]['shared_customers_count'] ?? null);
        $this->assertSame(1, $affinities[$eventC->event->uuid]['shared_customers_count'] ?? null);
    }

    #[Test]
    public function event_affinity_report_returns_404_for_event_from_another_tenant(): void
    {
        $response = $this->auth()->getJson('/api/v1/reports/analytics/event-affinity?event_uuid='.Str::uuid());

        $response->assertStatus(404);
    }

    private function attachTicketTypeToSale(TicketType $ticketType, int $clientId): void
    {
        $sale = $this->makeSale($clientId);
        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);
        $ticketType->loadMissing('event');
    }
}
