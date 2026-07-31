<?php

namespace Tests\Feature\Balcao;

use App\Models\AuditLog;
use App\Models\Balcao\Comanda;
use App\Models\Balcao\ComandaItem;
use App\Models\Balcao\Station;
use App\Models\Balcao\Table;
use App\Models\Balcao\TableReservation;
use App\Models\Balcao\TableWaitlist;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Models\Stock\StockBalance;
use App\Models\Stock\StockMovement;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class BalcaoTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('balcao-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Todas as permissões concedidas ANTES do primeiro request autenticado —
     * o conjunto é cacheado no primeiro CheckPermission (mesmo cuidado do PdvTest).
     */
    private function grantAll(): void
    {
        foreach (['read', 'create', 'update', 'delete', 'open', 'close', 'add_item', 'prep'] as $action) {
            $this->grantPermission('balcao', $action);
        }
        $this->grantPermission('stock', 'entry');
    }

    private function makeStation(string $type = 'kitchen', array $overrides = []): Station
    {
        return Station::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Station ' . Str::random(6),
            'type' => $type,
            'is_active' => true,
        ], $overrides));
    }

    private function makeProductForStation(?Station $station, float $price = 10, ?int $tenantId = null): Product
    {
        $tenantId ??= $this->tenant->id;

        $category = ProductCategory::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Category ' . Str::random(6),
            'is_active' => true,
            'station_id' => $station?->id,
        ]);

        $type = ProductType::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_category_id' => $category->id,
            'name' => 'Type ' . Str::random(6),
            'is_active' => true,
        ]);

        return Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_type_id' => $type->id,
            'name' => 'Product ' . Str::random(6),
            'price' => $price,
            'is_available' => true,
        ]);
    }

    private function openComanda(array $body = []): array
    {
        return $this->auth()->postJson('/api/v1/balcao/comandas', $body)
            ->assertStatus(201)->json('data');
    }

    private function addItem(string $comandaUuid, string $productUuid, float $qty = 1, ?string $notes = null): array
    {
        return $this->auth()->postJson("/api/v1/balcao/comandas/{$comandaUuid}/items", array_filter([
            'product_uuid' => $productUuid,
            'qty' => $qty,
            'notes' => $notes,
        ]))->assertStatus(201)->json('data');
    }

    private function createTable(array $overrides = []): Table
    {
        return Table::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'label' => 'Mesa ' . Str::random(4),
            'area' => 'Salão',
            'seats' => 4,
            'status' => Table::STATUS_FREE,
        ], $overrides));
    }

    private function setPrep(string $comandaUuid, string $itemUuid, string $status, ?string $reason = null)
    {
        return $this->auth()->patchJson(
            "/api/v1/balcao/comandas/{$comandaUuid}/items/{$itemUuid}/prep-status",
            array_filter(['prep_status' => $status, 'cancelled_reason' => $reason])
        );
    }

    #[Test]
    public function item_appears_only_in_its_category_station_queue(): void
    {
        $this->grantAll();

        $kitchen = $this->makeStation('kitchen');
        $bar = $this->makeStation('bar');

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $foodProduct = $this->makeProductForStation($kitchen, 10);
        $drinkProduct = $this->makeProductForStation($bar, 5);
        $this->stockEntry($this->tenant->id, $foodProduct, $location, 20);
        $this->stockEntry($this->tenant->id, $drinkProduct, $location, 20);

        $comanda = $this->openComanda();
        $foodItem = $this->addItem($comanda['uuid'], $foodProduct->uuid);
        $drinkItem = $this->addItem($comanda['uuid'], $drinkProduct->uuid);

        $this->setPrep($comanda['uuid'], $foodItem['uuid'], 'sent_to_station')->assertStatus(200);
        $this->setPrep($comanda['uuid'], $drinkItem['uuid'], 'sent_to_station')->assertStatus(200);

        $kitchenTickets = $this->auth()->getJson("/api/v1/balcao/stations/{$kitchen->uuid}/tickets")
            ->assertStatus(200)->json('data');
        $barTickets = $this->auth()->getJson("/api/v1/balcao/stations/{$bar->uuid}/tickets")
            ->assertStatus(200)->json('data');

        $this->assertCount(1, $kitchenTickets);
        $this->assertSame($foodProduct->uuid, $kitchenTickets[0]['product']['uuid']);

        $this->assertCount(1, $barTickets);
        $this->assertSame($drinkProduct->uuid, $barTickets[0]['product']['uuid']);
    }

    #[Test]
    public function prep_status_state_machine_rejects_invalid_transitions(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $item = $this->addItem($comanda['uuid'], $product->uuid);

        // queued -> ready (pulando o envio) é inválido.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'ready')
            ->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');

        // Envia (queued -> sent_to_station).
        $this->setPrep($comanda['uuid'], $item['uuid'], 'sent_to_station')->assertStatus(200);

        // sent_to_station -> ready (pulando preparing) é inválido.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'ready')
            ->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');

        // Caminho feliz completo.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'preparing')->assertStatus(200);
        $this->setPrep($comanda['uuid'], $item['uuid'], 'ready')->assertStatus(200);
        $this->setPrep($comanda['uuid'], $item['uuid'], 'delivered_to_table')->assertStatus(200);

        // Estado terminal: nenhuma transição parte dele.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'preparing')
            ->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');
    }

    #[Test]
    public function resending_an_already_sent_item_does_not_debit_stock_twice(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $item = $this->addItem($comanda['uuid'], $product->uuid, 2);

        $this->setPrep($comanda['uuid'], $item['uuid'], 'sent_to_station')->assertStatus(200);
        // Reenvio rejeitado (já saiu de queued).
        $this->setPrep($comanda['uuid'], $item['uuid'], 'sent_to_station')
            ->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');

        $this->assertSame(1, StockMovement::where('type', 'exit')
            ->where('product_id', $product->id)->count());
    }

    #[Test]
    public function closing_computes_total_with_service_fee_and_validates_split_payment(): void
    {
        $this->grantAll();

        TenantSettings::create([
            'tenant_id' => $this->tenant->id,
            'service_fee_percent' => 10,
            'service_fee_mandatory' => false,
        ]);

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        // Comanda congela 10% de taxa de serviço na abertura.
        $comanda = $this->openComanda();
        $item = $this->addItem($comanda['uuid'], $product->uuid, 2); // subtotal 20
        $this->setPrep($comanda['uuid'], $item['uuid'], 'sent_to_station')->assertStatus(200);

        // Soma errada é rejeitada e nada é materializado.
        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 20]], // falta a taxa (2)
        ])->assertStatus(422)->assertJsonPath('code', 'PAYMENT_AMOUNT_MISMATCH');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(Comanda::STATUS_OPEN, Comanda::where('uuid', $comanda['uuid'])->value('status'));

        // Split que bate: subtotal 20 + taxa 2 = 22.
        $response = $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [
                ['method' => 'cash', 'amount' => 12],
                ['method' => 'pix', 'amount' => 10],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '22.00')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true)
            ->assertJsonPath('data.origin', 'counter');
        $this->assertEqualsWithDelta(2.0, (float) $response->json('data.service_fee'), 0.001);

        $comandaModel = Comanda::where('uuid', $comanda['uuid'])->first();
        $this->assertSame(Comanda::STATUS_CLOSED, $comandaModel->status);
        $this->assertNotNull($comandaModel->order_id);

        // Estoque baixou só no envio (não repete no fechamento).
        $this->assertSame(1, StockMovement::where('type', 'exit')
            ->where('product_id', $product->id)->count());

        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)->first();
        $this->assertEqualsWithDelta(18, (float) $balance->quantity_available, 0.001);
    }

    #[Test]
    public function service_fee_can_be_refused_when_not_mandatory(): void
    {
        $this->grantAll();

        TenantSettings::create([
            'tenant_id' => $this->tenant->id,
            'service_fee_percent' => 10,
            'service_fee_mandatory' => false,
        ]);

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $this->addItem($comanda['uuid'], $product->uuid, 2); // subtotal 20

        $response = $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'apply_service_fee' => false,
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '20.00');
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.service_fee'), 0.001);
    }

    #[Test]
    public function mandatory_service_fee_cannot_be_refused(): void
    {
        $this->grantAll();

        TenantSettings::create([
            'tenant_id' => $this->tenant->id,
            'service_fee_percent' => 10,
            'service_fee_mandatory' => true,
        ]);

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $this->addItem($comanda['uuid'], $product->uuid, 2); // subtotal 20

        // Mesmo recusando, a taxa obrigatória entra: pagar só 20 falha.
        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'apply_service_fee' => false,
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ])->assertStatus(422)->assertJsonPath('code', 'PAYMENT_AMOUNT_MISMATCH');

        // Pagando 22 (com a taxa obrigatória) fecha.
        $mandatoryResponse = $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'apply_service_fee' => false,
            'payments' => [['method' => 'cash', 'amount' => 22]],
        ])->assertStatus(201);
        $this->assertEqualsWithDelta(2.0, (float) $mandatoryResponse->json('data.service_fee'), 0.001);
    }

    #[Test]
    public function closing_twice_is_rejected_and_does_not_create_a_second_order(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $this->addItem($comanda['uuid'], $product->uuid, 1); // total 10

        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(201);

        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');

        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function closing_releases_the_table_when_no_other_open_comanda_remains(): void
    {
        $this->grantAll();

        $table = Table::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'label' => 'Mesa 1',
            'status' => Table::STATUS_FREE,
        ]);

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comandaA = $this->openComanda(['table_uuid' => $table->uuid]);
        $comandaB = $this->openComanda(['table_uuid' => $table->uuid]);

        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);

        $this->addItem($comandaA['uuid'], $product->uuid, 1);
        $this->addItem($comandaB['uuid'], $product->uuid, 1);

        // Fecha a A: ainda há a B aberta -> mesa continua ocupada.
        $this->auth()->postJson("/api/v1/balcao/comandas/{$comandaA['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(201);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);

        // Fecha a B: sem outra aberta -> mesa liberada.
        $this->auth()->postJson("/api/v1/balcao/comandas/{$comandaB['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(201);
        $this->assertSame(Table::STATUS_FREE, $table->fresh()->status);
    }

    #[Test]
    public function cancelling_an_item_requires_and_audits_a_reason_and_returns_stock(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $item = $this->addItem($comanda['uuid'], $product->uuid, 2);
        $this->setPrep($comanda['uuid'], $item['uuid'], 'sent_to_station')->assertStatus(200);

        // Cancelar sem motivo é rejeitado na validação.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'cancelled')->assertStatus(422);

        // Com motivo: cancela e devolve o estoque baixado no envio.
        $this->setPrep($comanda['uuid'], $item['uuid'], 'cancelled', 'Cliente desistiu')
            ->assertStatus(200)
            ->assertJsonPath('data.prep_status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Cliente desistiu');

        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)->first();
        $this->assertEqualsWithDelta(20, (float) $balance->quantity_available, 0.001);

        $this->assertSame(1, AuditLog::where('event', 'comanda_item_cancelled')->count());
        $log = AuditLog::where('event', 'comanda_item_cancelled')->first();
        $this->assertSame('Cliente desistiu', $log->meta['reason']);

        // Item cancelado não entra no fechamento (comanda sem itens válidos).
        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(422)->assertJsonPath('code', 'COMANDA_ERROR');
    }

    #[Test]
    public function open_and_close_generate_audit_logs(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->makeProductForStation($station, 10);
        $this->stockEntry($this->tenant->id, $product, $location, 20);

        $comanda = $this->openComanda();
        $this->addItem($comanda['uuid'], $product->uuid, 1);

        $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(201);

        $this->assertSame(1, AuditLog::where('event', 'comanda_opened')->count());
        $this->assertSame(1, AuditLog::where('event', 'comanda_item_added')->count());
        $this->assertSame(1, AuditLog::where('event', 'comanda_closed')->count());
    }

    #[Test]
    public function opening_same_client_comanda_uuid_is_idempotent(): void
    {
        $this->grantAll();

        $payload = [
            'label' => 'Mesa offline',
            'client_comanda_uuid' => (string) Str::uuid(),
        ];

        $first = $this->auth()->postJson('/api/v1/balcao/comandas', $payload)->assertStatus(201);
        $second = $this->auth()->postJson('/api/v1/balcao/comandas', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('comandas', 1);
    }

    #[Test]
    public function adding_same_client_item_uuid_is_idempotent(): void
    {
        $this->grantAll();

        $station = $this->makeStation();
        $product = $this->makeProductForStation($station, 10);
        $comanda = $this->openComanda();
        $payload = [
            'product_uuid' => $product->uuid,
            'qty' => 2,
            'client_item_uuid' => (string) Str::uuid(),
        ];

        $first = $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/items", $payload)->assertStatus(201);
        $second = $this->auth()->postJson("/api/v1/balcao/comandas/{$comanda['uuid']}/items", $payload)->assertStatus(201);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('comanda_items', 1);
    }

    #[Test]
    public function offline_snapshot_returns_tables_comandas_and_available_products(): void
    {
        $this->grantAll();

        $table = Table::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'label' => 'Mesa offline',
            'status' => Table::STATUS_OCCUPIED,
        ]);

        $station = $this->makeStation();
        $availableProduct = $this->makeProductForStation($station, 10);
        $hiddenProduct = $this->makeProductForStation($station, 12);
        $hiddenProduct->is_available = false;
        $hiddenProduct->save();

        $comanda = $this->openComanda([
            'table_uuid' => $table->uuid,
            'label' => 'Comanda offline',
        ]);
        $this->addItem($comanda['uuid'], $availableProduct->uuid, 1);

        $response = $this->auth()->getJson('/api/v1/balcao/offline-snapshot');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.tables.0.uuid', $table->uuid)
            ->assertJsonPath('data.comandas.0.uuid', $comanda['uuid']);

        $products = collect($response->json('data.products'));
        $this->assertCount(1, $products);
        $this->assertSame($availableProduct->uuid, $products->first()['uuid']);
    }

    #[Test]
    public function balcao_resources_are_isolated_between_tenants(): void
    {
        $this->grantAll();

        // Tenant estrangeiro com estação e comanda próprios.
        $foreignTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Foreign Tenant',
            'slug' => 'foreign-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignStation = Station::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'name' => 'Estação Alheia',
            'type' => 'kitchen',
            'is_active' => true,
        ]);

        $foreignProduct = $this->makeProductForStation($foreignStation, 10, $foreignTenant->id);

        $foreignComanda = Comanda::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'status' => Comanda::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $foreignItem = ComandaItem::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'comanda_id' => $foreignComanda->id,
            'product_id' => $foreignProduct->id,
            'station_id' => $foreignStation->id,
            'qty' => 1,
            'unit_price' => 10,
            'prep_status' => ComandaItem::STATUS_SENT_TO_STATION,
            'sent_to_station_at' => now(),
        ]);

        // Produto local (do tenant A), para o addItem passar da validação de
        // request e chegar ao guard de posse da comanda (404), isolando o IDOR
        // da comanda do 422 de "produto de outro tenant".
        $localProduct = $this->makeProductForStation($this->makeStation(), 10);

        // Lista de comandas do tenant A não enxerga a de B.
        $this->auth()->getJson('/api/v1/balcao/comandas')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Tickets/close/addItem sobre recursos de B pelo A -> 404.
        $this->auth()->getJson("/api/v1/balcao/stations/{$foreignStation->uuid}/tickets")
            ->assertStatus(404);

        $this->auth()->postJson("/api/v1/balcao/comandas/{$foreignComanda->uuid}/items", [
            'product_uuid' => $localProduct->uuid,
            'qty' => 1,
        ])->assertStatus(404);

        $this->auth()->patchJson(
            "/api/v1/balcao/comandas/{$foreignComanda->uuid}/items/{$foreignItem->uuid}/prep-status",
            ['prep_status' => 'preparing']
        )->assertStatus(404);

        $this->auth()->postJson("/api/v1/balcao/comandas/{$foreignComanda->uuid}/close", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(404);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function reservation_assigns_table_and_marks_it_reserved(): void
    {
        $this->grantAll();
        $table = $this->createTable(['label' => 'Mesa 10', 'seats' => 4]);

        $response = $this->auth()->postJson('/api/v1/balcao/reservas', [
            'customer_name' => 'Maria',
            'customer_phone' => '11999999999',
            'party_size' => 3,
            'scheduled_for' => now()->addHours(2)->toISOString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer_name', 'Maria')
            ->assertJsonPath('data.table.uuid', $table->uuid)
            ->assertJsonPath('data.status', TableReservation::STATUS_CONFIRMED);

        $this->assertSame(Table::STATUS_RESERVED, $table->fresh()->status);
    }

    #[Test]
    public function seating_a_reservation_opens_comanda_and_marks_table_occupied(): void
    {
        $this->grantAll();
        $table = $this->createTable(['label' => 'Mesa 12', 'seats' => 6]);

        $reservationUuid = $this->auth()->postJson('/api/v1/balcao/reservas', [
            'customer_name' => 'Carlos',
            'party_size' => 4,
            'scheduled_for' => now()->addHour()->toISOString(),
        ])->assertStatus(201)->json('data.uuid');

        $response = $this->auth()->postJson("/api/v1/balcao/reservas/{$reservationUuid}/seat", [
            'label' => 'Carlos',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TableReservation::STATUS_SEATED)
            ->assertJsonPath('data.table.uuid', $table->uuid);

        $reservation = TableReservation::where('uuid', $reservationUuid)->firstOrFail();
        $this->assertNotNull($reservation->seated_comanda_id);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    #[Test]
    public function waitlist_entry_can_be_seated_and_open_a_comanda(): void
    {
        $this->grantAll();
        $table = $this->createTable(['label' => 'Mesa 14', 'seats' => 4]);

        $waitlistUuid = $this->auth()->postJson('/api/v1/balcao/fila-espera', [
            'customer_name' => 'Juliana',
            'party_size' => 2,
            'quoted_wait_minutes' => 15,
        ])->assertStatus(201)->json('data.uuid');

        $response = $this->auth()->postJson("/api/v1/balcao/fila-espera/{$waitlistUuid}/seat", [
            'table_uuid' => $table->uuid,
            'label' => 'Juliana',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TableWaitlist::STATUS_SEATED)
            ->assertJsonPath('data.table.uuid', $table->uuid);

        $entry = TableWaitlist::where('uuid', $waitlistUuid)->firstOrFail();
        $this->assertNotNull($entry->seated_comanda_id);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }
}
