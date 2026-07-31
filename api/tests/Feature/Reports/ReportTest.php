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
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockLocation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('report-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createClientWithCity(int $tenantId, string $cityName = 'Cidade Teste'): Client
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
            'name' => 'Bairro ' . Str::random(6),
        ]);

        $endereco = Endereco::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        return Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'name' => 'Client ' . Str::random(6),
            'is_trusted' => true,
            'is_active' => true,
        ]);
    }

    protected function createClientWithLocation(int $tenantId, string $cityName, string $neighborhoodName): Client
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
            'tenant_id' => $tenantId,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
        ]);

        return Client::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'endereco_id' => $endereco->id,
            'name' => 'Client ' . Str::random(6),
            'is_trusted' => true,
            'is_active' => true,
        ]);
    }

    protected function createLocation(int $tenantId): StockLocation
    {
        return StockLocation::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Location ' . Str::random(6),
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    protected function createProduct(int $tenantId, string $name = 'Produto Teste'): Product
    {
        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Categoria ' . Str::random(5),
            'is_active' => true,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_category_id' => $category->id,
            'name' => 'Tipo ' . Str::random(5),
            'is_active' => true,
        ]);

        return Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_type_id' => $type->id,
            'name' => $name,
            'price' => 10,
            'is_available' => true,
        ]);
    }

    protected function createOrder(int $tenantId, Client $client, StockLocation $location, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));
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

    // --- Indicadores ---

    #[Test]
    public function user_without_permission_cannot_view_indicators(): void
    {
        $this->auth()->getJson('/api/v1/reports/indicators')->assertStatus(403);
    }

    #[Test]
    public function indicators_exclude_cancelled_orders_and_count_correctly(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => true, 'paid_at' => now(), 'is_delivered' => true, 'delivered_at' => now(), 'total_amount' => 100,
        ]);
        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => false, 'is_delivered' => false, 'total_amount' => 50, 'due_date' => now()->subDays(3),
        ]);
        $this->createOrder($this->tenant->id, $client, $location, [
            'cancelled_at' => now(), 'cancellation_reason' => 'teste', 'total_amount' => 999,
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/indicators')->assertStatus(200);

        $response->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_sales_amount', '150.00')
            ->assertJsonPath('data.average_ticket', '75.00')
            ->assertJsonPath('data.delivered_orders', 1)
            ->assertJsonPath('data.undelivered_orders', 1)
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.unpaid_orders', 1)
            ->assertJsonPath('data.amount_received', '100.00')
            ->assertJsonPath('data.amount_receivable', '50.00')
            ->assertJsonPath('data.overdue_orders_count', 1);
    }

    #[Test]
    public function operation_health_aggregates_internal_attention(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $approvalAttentionOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'status' => 'pending_approval',
        ]);
        $approvalCriticalOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'status' => 'pending_approval',
        ]);
        $productionCriticalOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'status' => 'confirmed',
            'is_out_for_delivery' => false,
            'is_delivered' => false,
        ]);
        $dispatchAttentionOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'status' => 'confirmed',
            'is_out_for_delivery' => true,
            'is_delivered' => false,
        ]);
        $financialCriticalOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'status' => 'confirmed',
            'is_delivered' => true,
            'is_paid' => false,
        ]);

        $approvalAttentionOrder->forceFill(['created_at' => now()->subMinutes(6)])->saveQuietly();
        $approvalCriticalOrder->forceFill(['created_at' => now()->subMinutes(18)])->saveQuietly();
        $productionCriticalOrder->forceFill(['created_at' => now()->subMinutes(55)])->saveQuietly();
        $dispatchAttentionOrder->forceFill(['created_at' => now()->subMinutes(14)])->saveQuietly();
        $financialCriticalOrder->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        $response = $this->auth()->getJson('/api/v1/reports/operation-health')->assertStatus(200);

        $response->assertJsonPath('data.internal.approval.total', 2)
            ->assertJsonPath('data.internal.approval.attention', 1)
            ->assertJsonPath('data.internal.approval.critical', 1)
            ->assertJsonPath('data.internal.approval.oldest_minutes', 18)
            ->assertJsonPath('data.internal.production.total', 1)
            ->assertJsonPath('data.internal.production.critical', 1)
            ->assertJsonPath('data.internal.dispatch.total', 1)
            ->assertJsonPath('data.internal.dispatch.attention', 1)
            ->assertJsonPath('data.internal.financial_pending.total', 1)
            ->assertJsonPath('data.internal.financial_pending.critical', 1)
            ->assertJsonPath('data.totals.items_requiring_attention', 5)
            ->assertJsonPath('data.totals.critical_items', 3);
    }

    #[Test]
    public function amount_received_and_receivable_mix_non_installment_installment_and_cancelled(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        // Não-parcelado, pago -> conta 100 no recebido.
        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => true, 'paid_at' => now(), 'total_amount' => 100,
        ]);

        // Parcelado, total 90, 2 de 3 parcelas pagas (60 pago).
        $installmentOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'is_installment' => true, 'total_amount' => 90,
        ]);

        foreach ([['n' => 1, 'paid' => true], ['n' => 2, 'paid' => true], ['n' => 3, 'paid' => false]] as $i) {
            OrderInstallment::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'order_id' => $installmentOrder->id,
                'installment_number' => $i['n'],
                'amount' => 30,
                'due_date' => now()->addMonth(),
                'is_paid' => $i['paid'],
                'paid_at' => $i['paid'] ? now() : null,
            ]);
        }

        // Cancelado — não pode entrar em nenhuma soma, mesmo com valor alto.
        $this->createOrder($this->tenant->id, $client, $location, [
            'cancelled_at' => now(), 'cancellation_reason' => 'teste', 'total_amount' => 500,
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/indicators')->assertStatus(200);

        // total ativo = 100 + 90 = 190; recebido = 100 + 60 = 160; a receber = 30.
        $response->assertJsonPath('data.amount_received', '160.00')
            ->assertJsonPath('data.amount_receivable', '30.00');
    }

    #[Test]
    public function date_range_filter_restricts_indicators_to_period(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $inRange = $this->createOrder($this->tenant->id, $client, $location, ['total_amount' => 10]);
        $inRange->forceFill(['created_at' => Carbon::parse('2026-03-15 10:00:00')])->save();

        $outOfRange = $this->createOrder($this->tenant->id, $client, $location, ['total_amount' => 20]);
        $outOfRange->forceFill(['created_at' => Carbon::parse('2026-01-05 10:00:00')])->save();

        $response = $this->auth()->getJson('/api/v1/reports/indicators?date_from=2026-03-01&date_to=2026-03-31')
            ->assertStatus(200);

        $response->assertJsonPath('data.total_orders', 1);
    }

    #[Test]
    public function indicators_never_leak_data_from_another_tenant(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => true, 'paid_at' => now(), 'total_amount' => 500,
        ]);

        $this->setUpTenantScopedUser('report-user-other@test.com');
        $this->grantPermission('dashboard', 'read');

        $response = $this->auth()->getJson('/api/v1/reports/indicators')->assertStatus(200);

        $response->assertJsonPath('data.total_orders', 0)
            ->assertJsonPath('data.amount_received', '0.00')
            ->assertJsonPath('data.amount_receivable', '0.00');
    }

    // --- Gráficos ---

    #[Test]
    public function user_without_permission_cannot_view_charts(): void
    {
        $this->auth()->getJson('/api/v1/reports/charts')->assertStatus(403);
    }

    #[Test]
    public function charts_return_all_analytics_datasets_correctly(): void
    {
        $this->grantPermission('dashboard', 'read');
        $client = $this->createClientWithLocation($this->tenant->id, 'Campinas', 'Centro');
        $clientTwo = $this->createClientWithLocation($this->tenant->id, 'Santos', 'Ponta da Praia');
        $location = $this->createLocation($this->tenant->id);
        $productA = $this->createProduct($this->tenant->id, 'Queijo Minas');
        $productB = $this->createProduct($this->tenant->id, 'Doce de Leite');

        $firstOrder = $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => true,
            'paid_at' => now()->subDays(2),
            'is_delivered' => true,
            'delivered_at' => now()->subDays(5),
            'total_amount' => 100,
        ]);
        $secondOrder = $this->createOrder($this->tenant->id, $client, $location, ['total_amount' => 50, 'due_date' => now()->subDays(7)]);
        $thirdOrder = $this->createOrder($this->tenant->id, $clientTwo, $location, [
            'total_amount' => 70,
            'is_paid' => true,
            'paid_at' => now()->subDays(1),
            'is_delivered' => true,
            'delivered_at' => now()->subDays(4),
        ]);
        $thirdOrder->forceFill(['created_at' => Carbon::parse('2025-11-12 10:00:00')])->save();
        $this->createOrder($this->tenant->id, $client, $location, [
            'cancelled_at' => now(), 'cancellation_reason' => 'x', 'total_amount' => 999,
        ]);
        $installmentOrder = $this->createOrder($this->tenant->id, $clientTwo, $location, [
            'is_installment' => true,
            'total_amount' => 90,
        ]);
        OrderInstallment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'order_id' => $installmentOrder->id,
            'installment_number' => 1,
            'amount' => 45,
            'due_date' => now()->subDays(10),
            'is_paid' => false,
        ]);
        OrderInstallment::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'order_id' => $installmentOrder->id,
            'installment_number' => 2,
            'amount' => 45,
            'due_date' => now()->addDays(10),
            'is_paid' => false,
        ]);
        $this->attachOrderItem($firstOrder, $productA, 2, 20);
        $this->attachOrderItem($firstOrder, $productB, 3, 20);
        $this->attachOrderItem($secondOrder, $productA, 1, 50);
        $this->attachOrderItem($thirdOrder, $productB, 2, 35);

        $response = $this->auth()->getJson('/api/v1/reports/charts')->assertStatus(200);

        $response->assertJsonPath('data.paid_vs_unpaid.paid', 2)
            ->assertJsonPath('data.paid_vs_unpaid.unpaid', 2)
            ->assertJsonPath('data.delivered_vs_undelivered.delivered', 2)
            ->assertJsonPath('data.delivered_vs_undelivered.undelivered', 2)
            ->assertJsonPath('data.received_vs_receivable.received', '170.00')
            ->assertJsonPath('data.received_vs_receivable.receivable', '140.00')
            ->assertJsonCount(2, 'data.orders_by_month')
            ->assertJsonPath('data.orders_by_month.0.total_amount', '70.00')
            ->assertJsonPath('data.orders_by_city.0.city_name', 'Santos')
            ->assertJsonPath('data.orders_by_city.0.count', 2)
            ->assertJsonPath('data.orders_by_city.0.total_amount', '160.00')
            ->assertJsonPath('data.orders_by_neighborhood.0.neighborhood_name', 'Ponta da Praia')
            ->assertJsonPath('data.orders_by_neighborhood.0.total_amount', '160.00')
            ->assertJsonPath('data.seasonality_matrix.0.year', now()->year)
            ->assertJsonPath('data.seasonality_matrix.1.year', 2025)
            ->assertJsonPath('data.top_products.0.product_name', 'Doce de Leite')
            ->assertJsonPath('data.top_products.0.revenue', '130.00')
            ->assertJsonPath('data.top_clients.0.client_name', $clientTwo->name)
            ->assertJsonPath('data.top_clients.0.total_amount', '160.00')
            ->assertJsonPath('data.receivables_aging.0.bucket', 'current')
            ->assertJsonPath('data.receivables_aging.0.amount', '45.00')
            ->assertJsonPath('data.receivables_aging.1.bucket', 'overdue_1_30')
            ->assertJsonPath('data.receivables_aging.1.amount', '95.00')
            ->assertJsonPath('data.receivables_aging.1.count', 2)
            ->assertJsonPath('data.overdue_orders.0.source', 'installment')
            ->assertJsonPath('data.overdue_orders.0.amount', '45.00');

        // Soma por mês, não índice fixo: os dois recebíveis em aberto (due_date
        // now()-10d e now()+10d) caem no mesmo mês na maior parte do calendário,
        // mas perto da virada de mês um fica no mês anterior e o outro no
        // seguinte, gerando 2 buckets em vez de 1 — já quebrou este teste de
        // verdade por isso. O total agregado é o que importa e não depende de
        // quando o teste roda.
        $forecastTotal = array_sum(array_map(
            static fn (array $bucket): float => (float) $bucket['total_amount'],
            $response->json('data.receivables_forecast_by_month')
        ));
        $this->assertEqualsWithDelta(140.0, $forecastTotal, 0.001);

        $response->assertJsonFragment([
            'client_name' => $client->name,
            'frequency' => 2,
            'monetary' => '150.00',
        ]);

        $response->assertJsonFragment([
            'client_name' => $client->name,
            'avg_days_to_pay' => 3.0,
            'paid_orders_count' => 1,
        ]);

        $response->assertJsonFragment([
            'client_name' => $clientTwo->name,
            'revenue' => '160.00',
            'participation_percentage' => 51.61,
            'curve_class' => 'A',
        ]);

        $response->assertJsonFragment([
            'product_name' => 'Doce de Leite',
            'revenue' => '130.00',
            'curve_class' => 'A',
        ]);
    }

    // --- Listagem de pedidos filtrada ---

    #[Test]
    public function user_without_permission_cannot_list_report_orders(): void
    {
        $this->auth()->getJson('/api/v1/reports/orders')->assertStatus(403);
    }

    #[Test]
    public function report_orders_list_excludes_cancelled_and_supports_filters(): void
    {
        $this->grantPermission('reports', 'read');
        $clientA = $this->createClientWithCity($this->tenant->id, 'Campinas');
        $clientB = $this->createClientWithCity($this->tenant->id, 'Santos');
        $clientA->forceFill(['phone_primary' => '11999990001'])->save();
        $clientB->forceFill(['phone_primary' => '11999990002'])->save();
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $clientA, $location, ['is_paid' => true, 'paid_at' => now()]);
        $this->createOrder($this->tenant->id, $clientB, $location, ['is_paid' => false]);
        $this->createOrder($this->tenant->id, $clientA, $location, ['cancelled_at' => now(), 'cancellation_reason' => 'x']);

        $response = $this->auth()->getJson('/api/v1/reports/orders')->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $paidOnly = $this->auth()->getJson('/api/v1/reports/orders?is_paid=1')->assertStatus(200);
        $paidOnly->assertJsonCount(1, 'data');

        $byClient = $this->auth()->getJson('/api/v1/reports/orders?client_uuid=' . $clientB->uuid)->assertStatus(200);
        $byClient->assertJsonCount(1, 'data');
    }

    #[Test]
    public function user_without_permission_cannot_get_report_orders_summary(): void
    {
        $this->auth()->getJson('/api/v1/reports/orders/summary')->assertStatus(403);
    }

    #[Test]
    public function report_orders_summary_computes_percentages_over_filtered_base(): void
    {
        $this->grantPermission('reports', 'read');
        $client = $this->createClientWithCity($this->tenant->id, 'Campinas');
        $location = $this->createLocation($this->tenant->id);

        // Entregue + pago.
        $this->createOrder($this->tenant->id, $client, $location, [
            'is_delivered' => true, 'delivered_at' => now(), 'is_paid' => true, 'paid_at' => now(),
        ]);
        // Nem entregue nem pago, vencido (due_date no passado, não parcelado).
        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => false, 'is_delivered' => false, 'due_date' => now()->subDays(5)->toDateString(),
        ]);
        // Nem entregue nem pago, a vencer.
        $this->createOrder($this->tenant->id, $client, $location, [
            'is_paid' => false, 'is_delivered' => false, 'due_date' => now()->addDays(5)->toDateString(),
        ]);
        // Cancelado nunca conta.
        $this->createOrder($this->tenant->id, $client, $location, [
            'cancelled_at' => now(), 'cancellation_reason' => 'x',
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/orders/summary')->assertStatus(200);

        $response->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.delivered_percentage', 33.33)
            ->assertJsonPath('data.paid_percentage', 33.33)
            ->assertJsonPath('data.overdue_percentage', 33.33);
    }

    #[Test]
    public function report_orders_summary_returns_zeroes_without_orders(): void
    {
        $this->grantPermission('reports', 'read');

        $response = $this->auth()->getJson('/api/v1/reports/orders/summary')->assertStatus(200);

        $response->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.delivered_percentage', 0)
            ->assertJsonPath('data.paid_percentage', 0)
            ->assertJsonPath('data.overdue_percentage', 0);
    }

    // --- PDF ---

    #[Test]
    public function user_without_export_permission_cannot_download_orders_pdf(): void
    {
        $this->auth()->postJson('/api/v1/reports/orders/pdf')->assertStatus(403);
    }

    #[Test]
    public function orders_pdf_endpoint_returns_a_valid_pdf_download(): void
    {
        $this->grantPermission('reports', 'export_pdf');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);
        $this->createOrder($this->tenant->id, $client, $location, ['is_paid' => true, 'paid_at' => now()]);

        $response = $this->auth()->postJson('/api/v1/reports/orders/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

}
