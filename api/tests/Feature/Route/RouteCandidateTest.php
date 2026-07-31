<?php

namespace Tests\Feature\Route;

use App\Models\Client\Client;
use App\Models\Client\DiaIdeal;
use App\Models\Client\PeriodoIdeal;
use App\Models\Location\Bairro;
use App\Models\Location\Cidade;
use App\Models\Location\Endereco;
use App\Models\Location\Estado;
use App\Models\Order\Order;
use App\Models\Order\OrderInstallment;
use App\Models\Stock\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\GeneratesUniqueUf;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class RouteCandidateTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use GeneratesUniqueUf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('routes-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    protected function createClient(array $overrides = [], string $geocodeStatus = 'success'): Client
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
            'tenant_id' => $this->tenant->id,
            'estado_id' => $estado->id,
            'cidade_id' => $cidade->id,
            'bairro_id' => $bairro->id,
            'logradouro' => 'Rua Teste, 123',
            'is_active' => true,
            'lat' => $geocodeStatus === 'success' ? -21.8361344 : null,
            'lng' => $geocodeStatus === 'success' ? -48.1389045 : null,
            'geocode_status' => $geocodeStatus,
        ]);

        return Client::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'endereco_id' => $endereco->id,
            'name' => 'Cliente ' . Str::random(6),
            'phone_primary' => '11999990000',
            'is_trusted' => true,
            'is_active' => true,
        ], $overrides));
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

    protected function createOrder(Client $client, StockLocation $location, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'stock_location_id' => $location->id,
            'is_installment' => false,
            'total_amount' => 100,
            'is_paid' => false,
            'is_delivered' => false,
        ], $overrides));
    }

    protected function attachInstallment(Order $order, int $number, float $amount, string $dueDate, bool $isPaid = false): OrderInstallment
    {
        return OrderInstallment::create([
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

    #[Test]
    public function it_returns_delivery_candidates_for_the_selected_date(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $client = $this->createClient();
        $this->createOrder($client, $location, [
            'expected_delivery_date' => '2026-07-20',
            'is_delivered' => false,
        ]);

        // Outro dia: não deve entrar.
        $otherClient = $this->createClient();
        $this->createOrder($otherClient, $location, [
            'expected_delivery_date' => '2026-07-21',
            'is_delivered' => false,
        ]);

        // Já entregue no mesmo dia: DEVE entrar (permite desfazer entrega
        // marcada por engano na tela de rota, ver RouteCandidateService).
        $deliveredClient = $this->createClient();
        $this->createOrder($deliveredClient, $location, [
            'expected_delivery_date' => '2026-07-20',
            'is_delivered' => true,
        ]);

        // Cancelado: não deve entrar.
        $cancelledClient = $this->createClient();
        $this->createOrder($cancelledClient, $location, [
            'expected_delivery_date' => '2026-07-20',
            'is_delivered' => false,
            'cancelled_at' => now(),
        ]);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.stops');

        $this->assertSame('delivery', $response->json('data.type'));
        $this->assertSame('2026-07-20', $response->json('data.date'));
        $stopUuids = collect($response->json('data.stops'))->pluck('client_uuid')->all();
        $this->assertContains($client->uuid, $stopUuids);
        $this->assertContains($deliveredClient->uuid, $stopUuids);
        $this->assertNotContains($cancelledClient->uuid, $stopUuids);
    }

    /**
     * Fase 3.2: frontend precisa saber se pode oferecer o botão rápido de
     * "marcar pago" na tela de montagem de rota — só faz sentido para
     * pedido NÃO parcelado (pay()/unpay() rejeitam parcelado, exigem
     * payInstallment()/unpayInstallment() por parcela).
     */
    #[Test]
    public function it_includes_is_installment_flag_on_each_order_of_a_stop(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $client = $this->createClient();
        $this->createOrder($client, $location, [
            'expected_delivery_date' => '2026-07-20',
            'is_installment' => false,
        ]);

        $installmentClient = $this->createClient();
        $this->createOrder($installmentClient, $location, [
            'expected_delivery_date' => '2026-07-20',
            'is_installment' => true,
        ]);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.stops');

        $stops = collect($response->json('data.stops'));

        $nonInstallmentStop = $stops->firstWhere('client_uuid', $client->uuid);
        $this->assertFalse($nonInstallmentStop['orders'][0]['is_installment']);

        $installmentStop = $stops->firstWhere('client_uuid', $installmentClient->uuid);
        $this->assertTrue($installmentStop['orders'][0]['is_installment']);
    }

    #[Test]
    public function it_groups_multiple_orders_of_the_same_client_into_one_stop(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $client = $this->createClient();
        $this->createOrder($client, $location, ['expected_delivery_date' => '2026-07-20']);
        $this->createOrder($client, $location, ['expected_delivery_date' => '2026-07-20']);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.stops');

        $this->assertCount(2, $response->json('data.stops.0.orders'));
    }

    #[Test]
    public function it_returns_collection_candidates_including_overdue_installments(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $client = $this->createClient();
        $order = $this->createOrder($client, $location, ['is_installment' => true]);
        $this->attachInstallment($order, 1, 50, '2026-07-10'); // vencida (atrasada)
        $this->attachInstallment($order, 2, 50, '2026-07-20'); // vence no dia da rota

        // Parcela paga: não deve entrar.
        $paidClient = $this->createClient();
        $paidOrder = $this->createOrder($paidClient, $location, ['is_installment' => true]);
        $this->attachInstallment($paidOrder, 1, 100, '2026-07-15', true);

        // Pedido cancelado: não deve entrar mesmo com parcela vencida não paga.
        $cancelledClient = $this->createClient();
        $cancelledOrder = $this->createOrder($cancelledClient, $location, [
            'is_installment' => true,
            'cancelled_at' => now(),
        ]);
        $this->attachInstallment($cancelledOrder, 1, 100, '2026-07-15');

        // Vencimento futuro (depois da data filtrada): não deve entrar.
        $futureClient = $this->createClient();
        $futureOrder = $this->createOrder($futureClient, $location, ['is_installment' => true]);
        $this->attachInstallment($futureOrder, 1, 100, '2026-07-25');

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=collection&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.stops');

        $stop = $response->json('data.stops.0');
        $this->assertSame($client->uuid, $stop['client_uuid']);
        $this->assertSame([], $stop['orders']);
        $this->assertCount(2, $stop['installments']);

        $byDueDate = collect($stop['installments'])->keyBy('due_date');
        $this->assertTrue($byDueDate['2026-07-10']['is_overdue']);
        $this->assertFalse($byDueDate['2026-07-20']['is_overdue']);
    }

    /**
     * Permite desfazer um pagamento marcado por engano na própria tela de
     * rota: parcela paga NO MESMO dia filtrado continua aparecendo (com
     * is_paid=true, pro frontend oferecer "desfazer"). Paga em outro dia
     * (histórico antigo) não deve reaparecer — só o dia da rota importa.
     */
    #[Test]
    public function it_includes_installments_paid_on_the_filtered_date_but_not_older_ones(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $paidTodayClient = $this->createClient();
        $paidTodayOrder = $this->createOrder($paidTodayClient, $location, ['is_installment' => true]);
        $installment = $this->attachInstallment($paidTodayOrder, 1, 80, '2026-07-18');
        $installment->update(['is_paid' => true, 'paid_at' => '2026-07-20 10:00:00']);

        $paidLongAgoClient = $this->createClient();
        $paidLongAgoOrder = $this->createOrder($paidLongAgoClient, $location, ['is_installment' => true]);
        $oldInstallment = $this->attachInstallment($paidLongAgoOrder, 1, 80, '2026-07-05');
        $oldInstallment->update(['is_paid' => true, 'paid_at' => '2026-07-06 10:00:00']);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=collection&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.stops');

        $stop = $response->json('data.stops.0');
        $this->assertSame($paidTodayClient->uuid, $stop['client_uuid']);
        $this->assertTrue($stop['installments'][0]['is_paid']);
    }

    #[Test]
    public function it_includes_clients_without_successful_geocoding(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $client = $this->createClient([], 'pending');
        $this->createOrder($client, $location, ['expected_delivery_date' => '2026-07-20']);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.stops');

        $this->assertSame('pending', $response->json('data.stops.0.endereco.geocode_status'));
        $this->assertNull($response->json('data.stops.0.endereco.lat'));
    }

    #[Test]
    public function it_includes_dia_ideal_and_periodo_ideal_names_when_present(): void
    {
        $this->grantPermission('routes', 'read');
        $location = $this->createLocation();

        $diaIdeal = DiaIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Segunda',
            'is_active' => true,
        ]);

        $periodoIdeal = PeriodoIdeal::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Manhã',
            'is_active' => true,
        ]);

        $client = $this->createClient([
            'dia_ideal_id' => $diaIdeal->id,
            'periodo_ideal_id' => $periodoIdeal->id,
        ]);
        $this->createOrder($client, $location, ['expected_delivery_date' => '2026-07-20']);

        $response = $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(200);

        $this->assertSame('Segunda', $response->json('data.stops.0.dia_ideal_name'));
        $this->assertSame('Manhã', $response->json('data.stops.0.periodo_ideal_name'));
    }

    #[Test]
    public function it_denies_access_without_permission(): void
    {
        $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=2026-07-20')
            ->assertStatus(403);
    }

    #[Test]
    public function it_validates_type_and_date(): void
    {
        $this->grantPermission('routes', 'read');

        $this->auth()
            ->getJson('/api/v1/routes/candidates?type=invalid&date=2026-07-20')
            ->assertStatus(422);

        $this->auth()
            ->getJson('/api/v1/routes/candidates?type=delivery&date=20-07-2026')
            ->assertStatus(422);
    }
}
