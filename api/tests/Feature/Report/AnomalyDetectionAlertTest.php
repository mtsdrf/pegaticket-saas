<?php

namespace Tests\Feature\Report;

use App\Models\Sale\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Roadmap Fase A4 (docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md):
 * detecção de anomalia estatística (z-score simples) sobre receita e
 * volume de vendas diários, exposta em GET /reports/alerts junto dos
 * alertas já existentes (AlertService::statisticalAnomalyAlerts).
 */
class AnomalyDetectionAlertTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('anomaly-alerts@test.com');
        $this->grantPermission('dashboard', 'read');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function makeSaleOnDay(int $daysAgo, float $totalAmount): Sale
    {
        $client = $this->createClient($this->tenant->id);

        $sale = Sale::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'final_customer_id' => $client->id,
            'codigo' => 'SALE-'.Str::random(8),
            'total_amount' => $totalAmount,
            'paid_amount' => $totalAmount,
            'is_paid' => true,
            'paid_at' => now()->subDays($daysAgo),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        $sale->created_at = now()->subDays($daysAgo)->setTime(12, 0);
        $sale->save();

        return $sale;
    }

    #[Test]
    public function flags_a_revenue_day_that_is_far_outside_the_recent_pattern(): void
    {
        // Histórico com leve variação natural (95-105) em vez de constante:
        // desvio padrão zero deixa o z-score indefinido, e o service
        // deliberadamente não avalia nesse caso (ver AlertService::buildAnomalyAlert).
        $pattern = [95.0, 100.0, 105.0, 98.0, 102.0, 97.0, 103.0, 100.0, 96.0, 104.0];
        for ($daysAgo = 10; $daysAgo >= 1; $daysAgo--) {
            $this->makeSaleOnDay($daysAgo, $pattern[$daysAgo - 1]);
        }

        $this->makeSaleOnDay(0, 5000.0);

        $response = $this->auth()->getJson('/api/v1/reports/alerts');

        $response->assertStatus(200);
        $alerts = collect($response->json('data'));

        $anomaly = $alerts->firstWhere('type', 'daily_revenue_anomaly');
        $this->assertNotNull($anomaly);
        $this->assertSame(10, $anomaly['meta']['history_days']);
        $this->assertGreaterThan(2.0, abs($anomaly['meta']['z_score']));
    }

    #[Test]
    public function does_not_flag_a_day_that_matches_the_recent_pattern(): void
    {
        for ($daysAgo = 10; $daysAgo >= 1; $daysAgo--) {
            $this->makeSaleOnDay($daysAgo, 100.0);
            $this->makeSaleOnDay($daysAgo, 110.0);
        }

        $this->makeSaleOnDay(0, 105.0);
        $this->makeSaleOnDay(0, 108.0);

        $response = $this->auth()->getJson('/api/v1/reports/alerts');

        $response->assertStatus(200);
        $alerts = collect($response->json('data'));

        $this->assertNull($alerts->firstWhere('type', 'daily_revenue_anomaly'));
        $this->assertNull($alerts->firstWhere('type', 'daily_sales_count_anomaly'));
    }

    #[Test]
    public function does_not_flag_anything_when_tenant_history_is_shorter_than_the_minimum(): void
    {
        for ($daysAgo = 2; $daysAgo >= 1; $daysAgo--) {
            $this->makeSaleOnDay($daysAgo, 100.0);
        }

        $this->makeSaleOnDay(0, 5000.0);

        $response = $this->auth()->getJson('/api/v1/reports/alerts');

        $response->assertStatus(200);
        $alerts = collect($response->json('data'));

        $this->assertNull($alerts->firstWhere('type', 'daily_revenue_anomaly'));
        $this->assertNull($alerts->firstWhere('type', 'daily_sales_count_anomaly'));
        $this->assertNull($alerts->firstWhere('type', 'daily_payment_rejection_rate_anomaly'));
    }
}
