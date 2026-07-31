<?php

namespace Tests\Feature\Reports;

use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Order\OrderItem;
use App\Models\Plan\Plan;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockLocation;
use App\Models\Storefront\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('analytics-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // ------------------------------------------------------------------
    // Fixtures (mesmo padrão do ReportTest)
    // ------------------------------------------------------------------

    protected function createClientWithLocation(string $cityName = 'Cidade Teste', string $neighborhoodName = 'Bairro Teste'): Client
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => $cityName,
        ]);

        $bairro = Bairro::create([
            'uuid' => (string) Str::uuid(),
            'cidade_id' => $cidade->id,
            'name' => $neighborhoodName,
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        return Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
            'name' => 'Client ' . Str::random(6),
            'is_trusted' => true,
            'is_active' => true,
        ]);
    }

    protected function createLocation(): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    protected function createProduct(string $name = 'Produto Teste'): Product
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Categoria ' . Str::random(5),
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'name' => 'Tipo ' . Str::random(5),
            'is_active' => true,
        ]);

        return Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_type_id' => $type->id,
            'name' => $name,
            'price' => 10,
            'is_available' => true,
        ]);
    }

    protected function createOrder(Client $client, StockLocation $location, array $overrides = [], ?string $createdAt = null): Order
    {
        $order = Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));

        if ($createdAt) {
            DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
        }

        return $order->fresh();
    }

    protected function attachOrderItem(Order $order, Product $product, float $quantity, float $unitPrice): void
    {
        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ]);
    }

    protected function attachInstallment(Order $order, int $number, float $amount, string $dueDate, bool $isPaid = false): void
    {
        OrderInstallment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'installment_number' => $number,
            'amount' => $amount,
            'due_date' => $dueDate,
            'is_paid' => $isPaid,
            'paid_at' => $isPaid ? now() : null,
        ]);
    }

    protected function createFinalCustomer(): FinalCustomer
    {
        return FinalCustomer::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cliente Final ' . Str::random(5),
            'email' => Str::random(8) . '@final.test',
        ]);
    }

    protected function createStockBalance(
        Product $product,
        StockLocation $location,
        float $onHand,
        float $reserved = 0,
        float $blocked = 0
    ): StockBalance {
        return StockBalance::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_blocked' => $blocked,
        ]);
    }

    // ------------------------------------------------------------------
    // Permissão e gating de plano
    // ------------------------------------------------------------------

    #[Test]
    public function user_without_permission_cannot_access_analytics(): void
    {
        $this->auth()->getJson('/api/v1/reports/analytics/sales-summary')->assertStatus(403);
    }

    #[Test]
    public function tenant_on_basic_plan_gets_plan_upgrade_required(): void
    {
        $this->grantPermission('analytics', 'read');

        // A migration de plans já seeda basic/professional/premium
        // (sem pivots quando as functionalities ainda não existem, caso
        // do banco de teste) — reusar em vez de criar (slug é unique).
        $basic = Plan::firstOrCreate(
            ['slug' => 'basic'],
            ['name' => 'Básico', 'description' => 'Sem analytics', 'sort_order' => 10, 'is_active' => true]
        );

        // Plano basic tem outra functionality qualquer, mas NÃO analytics.
        $ordersFuncId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Orders',
            'slug' => 'orders',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $basic->id,
            'functionality_id' => $ordersFuncId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant->update(['plan_id' => $basic->id]);

        $response = $this->auth()->getJson('/api/v1/reports/analytics/sales-summary');

        $response->assertStatus(403)->assertJsonPath('code', 'PLAN_UPGRADE_REQUIRED');

        // Vinculando analytics ao plano, o mesmo request passa a responder 200.
        $analyticsFuncId = DB::table('functionalities')->where('slug', 'analytics')->value('id');

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) Str::uuid(),
            'plan_id' => $basic->id,
            'functionality_id' => $analyticsFuncId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auth()->getJson('/api/v1/reports/analytics/sales-summary')->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Endpoints
    // ------------------------------------------------------------------

    #[Test]
    public function sales_summary_groups_by_month_excludes_cancelled_and_returns_previous_period(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $this->createOrder($client, $location, ['total_amount' => 100], '2026-03-10 10:00:00');
        $this->createOrder($client, $location, ['total_amount' => 50], '2026-03-20 10:00:00');
        $this->createOrder($client, $location, [
            'total_amount' => 999, 'cancelled_at' => now(), 'cancellation_reason' => 'teste',
        ], '2026-03-15 10:00:00');

        // Período anterior de mesma duração (fev) tem 1 pedido de 70.
        $this->createOrder($client, $location, ['total_amount' => 70], '2026-02-10 10:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-summary?from=2026-03-01&to=2026-03-31&group_by=month')
            ->assertStatus(200);

        $response->assertJsonPath('data.group_by', 'month')
            ->assertJsonPath('data.current.total_orders', 2)
            ->assertJsonPath('data.current.total_revenue', '150.00')
            ->assertJsonPath('data.current.average_ticket', '75.00')
            ->assertJsonPath('data.current.buckets.0.bucket', '2026-03')
            ->assertJsonPath('data.current.buckets.0.order_count', 2)
            ->assertJsonPath('data.previous.total_orders', 1)
            ->assertJsonPath('data.previous.total_revenue', '70.00');

        $this->assertIsArray($response->json('data.previous.buckets'));
    }

    #[Test]
    public function sales_summary_groups_by_day(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $this->createOrder($client, $location, ['total_amount' => 40], '2026-03-10 08:00:00');
        $this->createOrder($client, $location, ['total_amount' => 60], '2026-03-10 18:00:00');
        $this->createOrder($client, $location, ['total_amount' => 25], '2026-03-11 09:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-summary?from=2026-03-01&to=2026-03-31&group_by=day')
            ->assertStatus(200);

        $response->assertJsonPath('data.current.buckets.0.bucket', '2026-03-10')
            ->assertJsonPath('data.current.buckets.0.order_count', 2)
            ->assertJsonPath('data.current.buckets.0.revenue', '100.00')
            ->assertJsonPath('data.current.buckets.1.bucket', '2026-03-11')
            ->assertJsonPath('data.current.buckets.1.order_count', 1);
    }

    #[Test]
    public function top_products_aggregates_order_items_and_excludes_cancelled_orders(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();
        $productA = $this->createProduct('Produto A');
        $productB = $this->createProduct('Produto B');

        $order = $this->createOrder($client, $location, ['total_amount' => 130]);
        $this->attachOrderItem($order, $productA, 10, 10); // 100
        $this->attachOrderItem($order, $productB, 3, 10);  // 30

        $cancelled = $this->createOrder($client, $location, [
            'total_amount' => 500, 'cancelled_at' => now(), 'cancellation_reason' => 'teste',
        ]);
        $this->attachOrderItem($cancelled, $productB, 50, 10);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/top-products?limit=10')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('Produto A', $data[0]['product_name']);
        $this->assertEquals(10, $data[0]['quantity_sold']);
        $this->assertSame('100.00', $data[0]['revenue']);
        $this->assertSame('Produto B', $data[1]['product_name']);
        $this->assertSame('30.00', $data[1]['revenue']);
    }

    #[Test]
    public function sales_by_location_groups_by_city_and_neighborhood(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $clientA = $this->createClientWithLocation('Cidade Alta', 'Bairro Norte');
        $clientB = $this->createClientWithLocation('Cidade Baixa', 'Bairro Sul');

        $this->createOrder($clientA, $location, ['total_amount' => 300]);
        $this->createOrder($clientA, $location, ['total_amount' => 100]);
        $this->createOrder($clientB, $location, ['total_amount' => 150]);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-location')
            ->assertStatus(200);

        $response->assertJsonPath('data.by_city.0.name', 'Cidade Alta')
            ->assertJsonPath('data.by_city.0.order_count', 2)
            ->assertJsonPath('data.by_city.0.revenue', '400.00')
            ->assertJsonPath('data.by_city.1.name', 'Cidade Baixa')
            ->assertJsonPath('data.by_neighborhood.0.name', 'Bairro Norte')
            ->assertJsonPath('data.by_neighborhood.1.name', 'Bairro Sul')
            ->assertJsonPath('data.by_neighborhood.1.revenue', '150.00');
    }

    #[Test]
    public function sales_history_returns_year_month_matrix_for_all_years(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $this->createOrder($client, $location, ['total_amount' => 200], '2025-05-10 10:00:00');
        $this->createOrder($client, $location, ['total_amount' => 80], '2026-01-15 10:00:00');
        $this->createOrder($client, $location, [
            'total_amount' => 999, 'cancelled_at' => now(), 'cancellation_reason' => 'teste',
        ], '2026-01-20 10:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-history')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertSame(2026, $data[0]['year']);
        $this->assertSame(2025, $data[1]['year']);
        $this->assertCount(12, $data[0]['months']);

        // Janeiro/2026: só o pedido não cancelado.
        $this->assertSame(1, $data[0]['months'][0]['count']);
        $this->assertSame('80.00', $data[0]['months'][0]['revenue']);

        // Maio/2025.
        $this->assertSame(1, $data[1]['months'][4]['count']);
        $this->assertSame('200.00', $data[1]['months'][4]['revenue']);

        // Mês sem venda vem zerado.
        $this->assertSame(0, $data[0]['months'][5]['count']);
    }

    #[Test]
    public function top_clients_returns_totals_last_order_and_rfm_label(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $clientA = $this->createClientWithLocation('Cidade A', 'Bairro A');
        $clientB = $this->createClientWithLocation('Cidade B', 'Bairro B');

        $this->createOrder($clientA, $location, ['total_amount' => 100], now()->subDays(2)->toDateTimeString());
        $this->createOrder($clientA, $location, ['total_amount' => 150], now()->subDays(5)->toDateTimeString());
        $this->createOrder($clientA, $location, ['total_amount' => 50], now()->subDays(9)->toDateTimeString());
        $this->createOrder($clientB, $location, ['total_amount' => 60], now()->subMonths(10)->toDateTimeString());

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/top-clients?limit=10')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($clientA->name, $data[0]['client_name']);
        $this->assertSame(3, $data[0]['order_count']);
        $this->assertSame('300.00', $data[0]['total_amount']);
        $this->assertSame(now()->subDays(2)->toDateString(), $data[0]['last_order_at']);

        foreach ($data as $row) {
            $this->assertContains($row['rfm_label'], ['vip', 'recorrente', 'em_risco', 'inativo']);
        }

        // Cliente A: melhor tercil em R, F e M -> vip.
        $this->assertSame('vip', $data[0]['rfm_label']);
    }

    #[Test]
    public function payment_delays_averages_days_between_delivery_and_payment(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $slowClient = $this->createClientWithLocation('Cidade Lenta', 'Bairro Lento');
        $fastClient = $this->createClientWithLocation('Cidade Rápida', 'Bairro Rápido');

        $this->createOrder($slowClient, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-01-01 10:00:00',
            'is_paid' => true, 'paid_at' => '2026-01-11 10:00:00',
        ], '2026-01-01 10:00:00');

        $this->createOrder($fastClient, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-01-05 10:00:00',
            'is_paid' => true, 'paid_at' => '2026-01-05 15:00:00',
        ], '2026-01-05 10:00:00');

        // Pedido sem paid_at não entra na média.
        $this->createOrder($slowClient, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-01-02 10:00:00',
        ], '2026-01-02 10:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/payment-delays?from=2026-01-01&to=2026-01-31')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($slowClient->name, $data[0]['client_name']);
        $this->assertEquals(10, $data[0]['avg_days_to_pay']);
        $this->assertSame(1, $data[0]['order_count']);
        $this->assertEquals(0, $data[1]['avg_days_to_pay']);
    }

    #[Test]
    public function overdue_orders_returns_payment_and_delivery_delays_sorted_desc(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();
        $client = $this->createClientWithLocation();

        // Parcelado com parcela vencida há 20 dias (não paga) + uma paga.
        $installmentOrder = $this->createOrder($client, $location, [
            'is_installment' => true, 'total_amount' => 200,
        ]);
        $this->attachInstallment($installmentOrder, 1, 100, now()->subDays(20)->toDateString());
        $this->attachInstallment($installmentOrder, 2, 100, now()->addDays(10)->toDateString());

        // Não parcelado com due_date vencido há 5 dias.
        $overduePayment = $this->createOrder($client, $location, [
            'total_amount' => 80, 'due_date' => now()->subDays(5)->toDateString(),
        ]);

        // Entrega prevista estourada há 3 dias, sem entrega.
        $overdueDelivery = $this->createOrder($client, $location, [
            'total_amount' => 60, 'expected_delivery_date' => now()->subDays(3)->toDateString(),
        ]);

        // Cancelado nunca aparece, mesmo vencido.
        $this->createOrder($client, $location, [
            'total_amount' => 999, 'due_date' => now()->subDays(30)->toDateString(),
            'cancelled_at' => now(), 'cancellation_reason' => 'teste',
        ]);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/overdue-orders')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertCount(3, $data);

        $this->assertSame($installmentOrder->uuid, $data[0]['order_uuid']);
        $this->assertSame('pagamento', $data[0]['type']);
        $this->assertSame('100.00', $data[0]['open_amount']);
        $this->assertSame(20, $data[0]['days_overdue']);

        $this->assertSame($overduePayment->uuid, $data[1]['order_uuid']);
        $this->assertSame('pagamento', $data[1]['type']);
        $this->assertSame(5, $data[1]['days_overdue']);

        $this->assertSame($overdueDelivery->uuid, $data[2]['order_uuid']);
        $this->assertSame('entrega', $data[2]['type']);
        $this->assertSame('60.00', $data[2]['open_amount']);
        $this->assertSame(3, $data[2]['days_overdue']);

        $response->assertJsonPath('meta.pagination.total', 3);
    }

    #[Test]
    public function abc_analysis_classifies_products_by_cumulative_revenue(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $productA = $this->createProduct('Produto A');
        $productB = $this->createProduct('Produto B');
        $productC = $this->createProduct('Produto C');

        $order = $this->createOrder($client, $location, ['total_amount' => 1000]);
        $this->attachOrderItem($order, $productA, 80, 10); // 800 -> 80% acumulado -> A
        $this->attachOrderItem($order, $productB, 15, 10); // 150 -> 95% acumulado -> B
        $this->attachOrderItem($order, $productC, 5, 10);  // 50 -> 100% acumulado -> C

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/abc-analysis?dimension=products')
            ->assertStatus(200);

        $response->assertJsonPath('data.dimension', 'products')
            ->assertJsonPath('data.total_revenue', '1000.00')
            ->assertJsonPath('data.items.0.name', 'Produto A')
            ->assertJsonPath('data.items.0.curve_class', 'A')
            ->assertJsonPath('data.items.1.curve_class', 'B')
            ->assertJsonPath('data.items.2.curve_class', 'C');
    }

    #[Test]
    public function abc_analysis_supports_clients_dimension(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $clientA = $this->createClientWithLocation('Cidade A', 'Bairro A');
        $clientB = $this->createClientWithLocation('Cidade B', 'Bairro B');

        $this->createOrder($clientA, $location, ['total_amount' => 900]);
        $this->createOrder($clientB, $location, ['total_amount' => 100]);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/abc-analysis?dimension=clients')
            ->assertStatus(200);

        $response->assertJsonPath('data.dimension', 'clients')
            ->assertJsonPath('data.items.0.name', $clientA->name)
            ->assertJsonPath('data.items.0.participation_percentage', 90)
            ->assertJsonPath('data.items.0.curve_class', 'B')
            ->assertJsonPath('data.items.1.name', $clientB->name)
            ->assertJsonPath('data.items.1.curve_class', 'C');
    }

    // ------------------------------------------------------------------
    // Novos indicadores (reforma BI)
    // ------------------------------------------------------------------

    #[Test]
    public function margin_summary_computes_gross_margin_and_coverage(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $withCost = $this->createProduct('Com custo');
        $withCost->update(['last_purchase_cost' => 6]);
        $noCost = $this->createProduct('Sem custo');

        $order = $this->createOrder($client, $location, ['total_amount' => 200]);
        $this->attachOrderItem($order, $withCost, 10, 10); // line_total 100, custo 60
        $this->attachOrderItem($order, $noCost, 10, 10);   // line_total 100, sem custo

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/margin-summary')
            ->assertStatus(200);

        $response->assertJsonPath('data.total_revenue', '200.00')
            ->assertJsonPath('data.total_revenue_with_known_cost', '100.00')
            ->assertJsonPath('data.total_cost', '60.00')
            ->assertJsonPath('data.gross_margin_amount', '40.00')
            ->assertJsonPath('data.gross_margin_percentage', 40)
            ->assertJsonPath('data.coverage_percentage', 50);
    }

    #[Test]
    public function margin_summary_returns_zeroes_without_orders(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/margin-summary')
            ->assertStatus(200);

        $response->assertJsonPath('data.total_revenue', '0.00')
            ->assertJsonPath('data.gross_margin_percentage', 0)
            ->assertJsonPath('data.coverage_percentage', 0);
    }

    #[Test]
    public function coupon_roi_compares_ticket_with_and_without_coupon(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        $coupon = Coupon::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'CUP10',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
        ]);

        // 2 pedidos com cupom: ticket médio 150.
        $this->createOrder($client, $location, ['total_amount' => 200, 'coupon_id' => $coupon->id, 'discount_amount' => 10]);
        $this->createOrder($client, $location, ['total_amount' => 100, 'coupon_id' => $coupon->id, 'discount_amount' => 10]);
        // 1 pedido sem cupom: ticket médio 100.
        $this->createOrder($client, $location, ['total_amount' => 100]);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/coupon-roi')
            ->assertStatus(200);

        $response->assertJsonPath('data.orders_with_coupon.count', 2)
            ->assertJsonPath('data.orders_with_coupon.average_ticket', '150.00')
            ->assertJsonPath('data.orders_without_coupon.count', 1)
            ->assertJsonPath('data.orders_without_coupon.average_ticket', '100.00')
            ->assertJsonPath('data.total_discount_amount', '20.00')
            ->assertJsonPath('data.ticket_lift_percentage', 50);
    }

    #[Test]
    public function coupon_roi_returns_zero_lift_without_orders(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/coupon-roi')
            ->assertStatus(200);

        $response->assertJsonPath('data.orders_with_coupon.count', 0)
            ->assertJsonPath('data.orders_with_coupon.average_ticket', '0.00')
            ->assertJsonPath('data.orders_without_coupon.average_ticket', '0.00')
            ->assertJsonPath('data.ticket_lift_percentage', 0);
    }

    #[Test]
    public function revenue_concentration_computes_top10_share(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $clientA = $this->createClientWithLocation('Cidade A', 'Bairro A');
        $clientB = $this->createClientWithLocation('Cidade B', 'Bairro B');

        $this->createOrder($clientA, $location, ['total_amount' => 800]);
        $this->createOrder($clientB, $location, ['total_amount' => 200]);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/revenue-concentration')
            ->assertStatus(200);

        // Só 2 clientes -> top 10 == todos -> 100%.
        $response->assertJsonPath('data.total_revenue', '1000.00')
            ->assertJsonPath('data.top10_revenue', '1000.00')
            ->assertJsonPath('data.concentration_percentage', 100);
    }

    #[Test]
    public function revenue_concentration_returns_zero_without_orders(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/revenue-concentration')
            ->assertStatus(200);

        $response->assertJsonPath('data.total_revenue', '0.00')
            ->assertJsonPath('data.concentration_percentage', 0);
    }

    #[Test]
    public function delivery_otif_computes_on_time_rate(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        // No prazo: entregue antes da data prevista.
        $this->createOrder($client, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-03-10 10:00:00',
            'expected_delivery_date' => '2026-03-12',
        ], '2026-03-01 10:00:00');

        // Atrasado: entregue depois da data prevista.
        $this->createOrder($client, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-03-15 10:00:00',
            'expected_delivery_date' => '2026-03-12',
        ], '2026-03-01 10:00:00');

        // Sem data prevista: não elegível.
        $this->createOrder($client, $location, [
            'is_delivered' => true, 'delivered_at' => '2026-03-10 10:00:00',
        ], '2026-03-01 10:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/delivery-otif?from=2026-03-01&to=2026-03-31')
            ->assertStatus(200);

        $response->assertJsonPath('data.eligible_orders', 2)
            ->assertJsonPath('data.on_time_orders', 1)
            ->assertJsonPath('data.otif_percentage', 50);
    }

    #[Test]
    public function delivery_otif_returns_zero_without_eligible_orders(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/delivery-otif')
            ->assertStatus(200);

        $response->assertJsonPath('data.eligible_orders', 0)
            ->assertJsonPath('data.otif_percentage', 0);
    }

    #[Test]
    public function churn_clients_lists_inactive_clients_with_revenue_at_risk(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        $churned = $this->createClientWithLocation('Cidade Churn', 'Bairro Churn');
        // 2 pedidos, o último há 90 dias (> 60 dias de inatividade).
        $this->createOrder($churned, $location, ['total_amount' => 300], now()->subDays(120)->toDateTimeString());
        $this->createOrder($churned, $location, ['total_amount' => 300], now()->subDays(90)->toDateTimeString());

        // Cliente ativo (comprou ontem) não é churn.
        $active = $this->createClientWithLocation('Cidade Ativa', 'Bairro Ativo');
        $this->createOrder($active, $location, ['total_amount' => 100], now()->subDay()->toDateTimeString());
        $this->createOrder($active, $location, ['total_amount' => 100], now()->subDays(3)->toDateTimeString());

        // Cliente com 1 pedido só não entra (mínimo 2).
        $single = $this->createClientWithLocation('Cidade Single', 'Bairro Single');
        $this->createOrder($single, $location, ['total_amount' => 500], now()->subDays(200)->toDateTimeString());

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/churn-clients')
            ->assertStatus(200);

        $response->assertJsonPath('data.churned_clients_count', 1)
            ->assertJsonPath('data.top_clients.0.client_name', $churned->name)
            // Janela 90 dias antes do último pedido: só o pedido de 300 há 120 dias entra (o de 90 dias é o próprio último, também dentro da janela) -> 600 / 3 = 200.
            ->assertJsonPath('data.top_clients.0.monthly_revenue_at_risk', '200.00');
    }

    #[Test]
    public function churn_clients_returns_empty_without_churned(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/churn-clients')
            ->assertStatus(200);

        $response->assertJsonPath('data.churned_clients_count', 0)
            ->assertJsonPath('data.estimated_monthly_revenue_at_risk', '0.00')
            ->assertJsonPath('data.top_clients', []);
    }

    #[Test]
    public function stalled_products_lists_products_with_stock_and_no_recent_sales(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        // Encalhado: tem estoque, sem venda nos últimos 60 dias.
        $stalled = $this->createProduct('Encalhado');
        $stalled->update(['last_purchase_cost' => 4]);
        $this->createStockBalance($stalled, $location, 10);

        // Vendido recentemente: tem estoque mas vendeu há 5 dias -> não encalhado.
        $moving = $this->createProduct('Girando');
        $this->createStockBalance($moving, $location, 20);
        $recentOrder = $this->createOrder($client, $location, [], now()->subDays(5)->toDateTimeString());
        $this->attachOrderItem($recentOrder, $moving, 2, 10);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/stalled-products')
            ->assertStatus(200);

        $response->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.product_name', 'Encalhado')
            ->assertJsonPath('data.items.0.quantity_on_hand', 10)
            ->assertJsonPath('data.items.0.value_tied_up', '40.00')
            ->assertJsonPath('data.items.0.cost_is_estimated', false)
            ->assertJsonPath('data.total_value_tied_up', '40.00');
    }

    #[Test]
    public function stalled_products_uses_price_fallback_and_flags_estimate(): void
    {
        $this->grantPermission('analytics', 'read');
        $location = $this->createLocation();

        // Produto sem last_purchase_cost -> usa price (10) e marca estimado.
        $stalled = $this->createProduct('Sem custo cadastrado');
        $this->createStockBalance($stalled, $location, 5);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/stalled-products')
            ->assertStatus(200);

        $response->assertJsonPath('data.items.0.value_tied_up', '50.00')
            ->assertJsonPath('data.items.0.cost_is_estimated', true);
    }

    #[Test]
    public function stalled_products_returns_empty_without_stock(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/stalled-products')
            ->assertStatus(200);

        $response->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.total_value_tied_up', '0.00')
            ->assertJsonPath('data.items', []);
    }

    #[Test]
    public function stock_ruptures_lists_sold_products_with_no_available_stock(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        // Rompido: vendeu nos últimos 90 dias e está com disponível <= 0.
        $ruptured = $this->createProduct('Rompido');
        $this->createStockBalance($ruptured, $location, 5, 5); // on_hand 5, reserved 5 -> disponível 0
        $order = $this->createOrder($client, $location, [], now()->subDays(10)->toDateTimeString());
        $this->attachOrderItem($order, $ruptured, 8, 10);

        // Com estoque disponível: vendeu mas ainda tem saldo -> não é ruptura.
        $inStock = $this->createProduct('Com saldo');
        $this->createStockBalance($inStock, $location, 30);
        $this->attachOrderItem($order, $inStock, 3, 10);

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/stock-ruptures')
            ->assertStatus(200);

        $response->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.items.0.product_name', 'Rompido')
            ->assertJsonPath('data.items.0.units_sold_last_90_days', 8);
    }

    #[Test]
    public function stock_ruptures_returns_empty_without_sales(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/stock-ruptures')
            ->assertStatus(200);

        $response->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.items', []);
    }

    #[Test]
    public function sales_by_hour_groups_by_weekday_and_hour(): void
    {
        $this->grantPermission('analytics', 'read');
        $client = $this->createClientWithLocation();
        $location = $this->createLocation();

        // 2026-03-10 é uma terça-feira -> DAYOFWEEK 3.
        $this->createOrder($client, $location, ['total_amount' => 40], '2026-03-10 08:00:00');
        $this->createOrder($client, $location, ['total_amount' => 60], '2026-03-10 08:30:00');
        $this->createOrder($client, $location, ['total_amount' => 25], '2026-03-10 14:00:00');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-hour?from=2026-03-01&to=2026-03-31')
            ->assertStatus(200);

        $cells = $response->json('data.cells');

        $this->assertCount(2, $cells);
        $this->assertSame(3, $cells[0]['day_of_week']);
        $this->assertSame(8, $cells[0]['hour']);
        $this->assertSame(2, $cells[0]['count']);
        $this->assertSame('100.00', $cells[0]['total_amount']);
        $this->assertSame(14, $cells[1]['hour']);
        $this->assertSame(1, $cells[1]['count']);
    }

    #[Test]
    public function sales_by_hour_returns_empty_cells_without_orders(): void
    {
        $this->grantPermission('analytics', 'read');

        $response = $this->auth()
            ->getJson('/api/v1/reports/analytics/sales-by-hour')
            ->assertStatus(200);

        $response->assertJsonPath('data.cells', []);
    }
}
