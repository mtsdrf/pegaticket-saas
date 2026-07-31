<?php

namespace Tests\Feature\Accounting;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Accounting\Concerns\CreatesAccountingFixtures;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class AccountingReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAccountingFixtures;
    use CreatesOrderFixtures;
    use SetsUpTenantScopedUser;

    private Tenant $reportTenant;
    private Product $product;
    private array $officeHeaders;

    private function seedScenario(): void
    {
        // SetsUpTenantScopedUser dá acesso ao stock/entry helper (não usado
        // aqui) e cria o tenant scoped; usamos esse tenant como alvo.
        $this->setUpTenantScopedUser('report-owner@example.com');
        $this->reportTenant = $this->tenant;

        $location = $this->createLocation($this->reportTenant->id);
        $client = $this->createClient($this->reportTenant->id);
        $this->product = $this->createProduct($this->reportTenant->id, [
            'price' => 100,
            'last_purchase_cost' => 40,
        ]);

        // 2 pedidos pagos (100 + 250 = 350), 1 pedido cancelado (ignorado).
        $this->makeOrder($client->id, $location->id, 100, paid: true, itemQty: 1);
        $this->makeOrder($client->id, $location->id, 250, paid: true, itemQty: 2);
        $cancelled = $this->makeOrder($client->id, $location->id, 999, paid: false, itemQty: 1);
        $cancelled->forceFill(['cancelled_at' => now()])->save();

        $office = $this->makeOffice();
        $this->approveLink($office, $this->reportTenant);
        $this->officeHeaders = ['Authorization' => 'Bearer ' . $this->officeToken($office)];
    }

    private function makeOrder(int $clientId, int $locationId, float $total, bool $paid, int $itemQty): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->reportTenant->id,
            'client_id' => $clientId,
            'stock_location_id' => $locationId,
            'is_installment' => false,
            'total_amount' => $total,
            'is_paid' => $paid,
            'paid_at' => $paid ? now() : null,
            'is_delivered' => false,
        ]);

        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->reportTenant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => $itemQty,
            'unit_price' => 100,
            'line_total' => 100 * $itemQty,
        ]);

        return $order;
    }

    #[Test]
    public function sales_report_sums_non_cancelled_orders(): void
    {
        $this->seedScenario();

        $this->withHeaders($this->officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->reportTenant->uuid}/reports/sales")
            ->assertStatus(200)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_revenue', '350.00');
    }

    #[Test]
    public function cash_flow_lists_only_paid_orders(): void
    {
        $this->seedScenario();

        $this->withHeaders($this->officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->reportTenant->uuid}/reports/cash-flow")
            ->assertStatus(200)
            ->assertJsonPath('data.total_in', '350.00');
    }

    #[Test]
    public function dre_computes_revenue_minus_product_cost(): void
    {
        $this->seedScenario();

        // Receita 350; custo = (1*40) + (2*40) = 120; lucro bruto 230.
        $this->withHeaders($this->officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->reportTenant->uuid}/reports/dre")
            ->assertStatus(200)
            ->assertJsonPath('data.revenue', '350.00')
            ->assertJsonPath('data.product_cost', '120.00')
            ->assertJsonPath('data.gross_profit', '230.00');
    }

    #[Test]
    public function every_report_read_is_audited(): void
    {
        $this->seedScenario();

        DB::table('audit_logs')->delete();

        $this->withHeaders($this->officeHeaders)
            ->getJson("/api/v1/accounting/tenants/{$this->reportTenant->uuid}/reports/sales")
            ->assertStatus(200);

        $log = DB::table('audit_logs')
            ->where('event', 'accounting_office.viewed_report')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);

        $meta = json_decode($log->meta, true);
        $this->assertSame('sales', $meta['report']);
        $this->assertSame($this->reportTenant->id, $meta['tenant_id']);
        $this->assertArrayHasKey('accounting_office_uuid', $meta);
    }

    #[Test]
    public function sales_report_exports_csv(): void
    {
        $this->seedScenario();

        $response = $this->withHeaders($this->officeHeaders)
            ->get("/api/v1/accounting/tenants/{$this->reportTenant->uuid}/reports/sales?format=csv");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('order_uuid,client_name', $body);
    }
}
