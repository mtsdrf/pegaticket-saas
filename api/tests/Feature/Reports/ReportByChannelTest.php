<?php

namespace Tests\Feature\Reports;

use App\Models\Client\Client;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Stock\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Roadmap A1.3 — GET /reports/by-channel, agregado por orders.origin.
 * O drill-down é o endpoint de listagem já existente (GET /orders?origin=
 * ...&date_from=...&date_to=...), sem rota nova — coberto aqui só a
 * confirmação de que o filtro `origin` já funciona (regressão), a
 * agregação em si é testada em OrderTest.php/rotas de orders já
 * existentes.
 */
class ReportByChannelTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('by-channel-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createClientWithCity(int $tenantId): Client
    {
        $estado = Estado::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Estado ' . Str::random(6),
            'uf' => $this->nextUf(),
        ]);

        $cidade = Cidade::create([
            'uuid' => (string) Str::uuid(),
            'estado_id' => $estado->id,
            'name' => 'Cidade ' . Str::random(6),
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
            'origin' => 'staff',
        ], $overrides));
    }

    #[Test]
    public function user_without_permission_cannot_view_by_channel_report(): void
    {
        $this->auth()->getJson('/api/v1/reports/by-channel')->assertStatus(403);
    }

    #[Test]
    public function aggregates_orders_by_origin_excluding_cancelled(): void
    {
        $this->grantPermission('reports', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'staff', 'total_amount' => 100]);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'staff', 'total_amount' => 200]);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'storefront', 'total_amount' => 50]);
        $this->createOrder($this->tenant->id, $client, $location, [
            'origin' => 'pdv',
            'total_amount' => 999,
            'cancelled_at' => now(),
        ]);

        $response = $this->auth()->getJson('/api/v1/reports/by-channel');

        $response->assertStatus(200);
        $data = $response->json('data');

        $byOrigin = collect($data)->keyBy('origin');

        $this->assertEquals(2, $byOrigin['staff']['order_count']);
        $this->assertEquals('300.00', $byOrigin['staff']['total_amount']);
        $this->assertEquals('150.00', $byOrigin['staff']['average_ticket']);

        $this->assertEquals(1, $byOrigin['storefront']['order_count']);
        $this->assertEquals('50.00', $byOrigin['storefront']['total_amount']);

        $this->assertFalse($byOrigin->has('pdv'), 'pedido cancelado não deve entrar na agregação');
    }

    #[Test]
    public function drill_down_via_orders_list_filters_by_origin_and_period(): void
    {
        $this->grantPermission('orders', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'staff']);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'pdv']);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'pdv']);

        $response = $this->auth()->getJson('/api/v1/orders?origin=pdv');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        collect($response->json('data'))->each(
            fn (array $order) => $this->assertEquals('pdv', $order['origin'])
        );
    }

    #[Test]
    public function drill_down_via_reports_orders_filters_by_origin_and_period(): void
    {
        $this->grantPermission('reports', 'read');
        $client = $this->createClientWithCity($this->tenant->id);
        $location = $this->createLocation($this->tenant->id);

        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'staff']);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'pdv']);
        $this->createOrder($this->tenant->id, $client, $location, ['origin' => 'pdv']);

        $response = $this->auth()->getJson('/api/v1/reports/orders?origin=pdv');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        collect($response->json('data'))->each(
            fn (array $order) => $this->assertEquals('pdv', $order['origin'])
        );

        $summary = $this->auth()->getJson('/api/v1/reports/orders/summary?origin=pdv')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals(2, $summary['total']);
    }
}
