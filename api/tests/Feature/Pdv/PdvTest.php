<?php

namespace Tests\Feature\Pdv;

use App\Models\AuditLog;
use App\Models\Pdv\CashRegister;
use App\Models\Pdv\CashSession;
use App\Models\Stock\StockBalance;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class PdvTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantScopedUser('pdv-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * O conjunto de permissões do usuário é cacheado no PRIMEIRO request que
     * passa pelo CheckPermission — conceder permissão via DB depois disso não
     * invalida o cache. Por isso os testes concedem tudo ANTES do primeiro
     * request autenticado (mesmo cuidado do padrão de OrderTest).
     */
    private function grantAllPdvPermissions(): void
    {
        foreach (['read', 'open', 'close', 'movement', 'sell'] as $action) {
            $this->grantPermission('pdv', $action);
        }
        $this->grantPermission('stock', 'entry');
    }

    private function openSession(float $openingAmount = 100.0): array
    {
        return $this->auth()->postJson('/api/v1/pdv/cash-sessions', [
            'opening_amount' => $openingAmount,
        ])->assertStatus(201)->json('data');
    }

    #[Test]
    public function cannot_sell_without_an_open_cash_session(): void
    {
        $this->grantAllPdvPermissions();
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'CASH_SESSION_ERROR');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function sale_with_matching_split_payment_marks_order_paid_and_decrements_stock(): void
    {
        $this->grantAllPdvPermissions();
        $this->openSession();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 2]],
            'payments' => [
                ['method' => 'cash', 'amount' => 12],
                ['method' => 'pix', 'amount' => 8],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '20.00')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.is_delivered', true)
            ->assertJsonPath('data.origin', 'pdv');

        $orderUuid = $response->json('data.uuid');

        // Duas linhas de pagamento paid vinculadas ao pedido.
        $order = \App\Models\Order\Order::where('uuid', $orderUuid)->first();
        $this->assertSame(2, Payment::where('payable_type', $order->getMorphClass())
            ->where('payable_id', $order->id)
            ->where('status', 'paid')
            ->count());

        // Estoque baixou de verdade (50 - 2 = 48 disponível).
        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEqualsWithDelta(48, (float) $balance->quantity_available, 0.001);

        // Vinculado à sessão de caixa.
        $this->assertNotNull($order->cash_session_id);
    }

    #[Test]
    public function sale_with_mismatching_payment_is_rejected_and_order_not_persisted(): void
    {
        $this->grantAllPdvPermissions();
        $this->openSession();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 2]],
            // Total é 20; soma paga é 15 -> rejeita.
            'payments' => [['method' => 'cash', 'amount' => 15]],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'PAYMENT_AMOUNT_MISMATCH');

        // Transação inteira revertida: nem pedido, nem pagamento, nem baixa.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertEqualsWithDelta(50, (float) $balance->quantity_available, 0.001);
    }

    #[Test]
    public function sale_without_client_uses_a_walk_in_consumer(): void
    {
        $this->grantAllPdvPermissions();
        $this->openSession();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $response = $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.client.name', 'Consumidor Final');
        $this->assertDatabaseHas('clients', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Consumidor Final',
        ]);
    }

    #[Test]
    public function sale_with_same_client_sale_uuid_is_idempotent(): void
    {
        $this->grantAllPdvPermissions();
        $this->openSession();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $payload = [
            'client_sale_uuid' => (string) Str::uuid(),
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ];

        $first = $this->auth()->postJson('/api/v1/pdv/sales', $payload)->assertStatus(201);
        $second = $this->auth()->postJson('/api/v1/pdv/sales', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);

        $balance = StockBalance::where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $this->assertEqualsWithDelta(48, (float) $balance->quantity_available, 0.001);
    }

    #[Test]
    public function offline_snapshot_returns_open_session_and_available_products(): void
    {
        $this->grantAllPdvPermissions();
        $session = $this->openSession(75.0);

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $available = $this->createProduct($this->tenant->id, ['name' => 'Produto Offline', 'price' => 12, 'is_available' => true]);
        $hidden = $this->createProduct($this->tenant->id, ['name' => 'Produto Inativo', 'price' => 9, 'is_available' => false]);
        $this->stockEntry($this->tenant->id, $available, $location, 8);
        $this->stockEntry($this->tenant->id, $hidden, $location, 5);

        $response = $this->auth()->getJson('/api/v1/pdv/offline-snapshot');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.cash_session.uuid', $session['uuid'])
            ->assertJsonPath('data.offline_payment_methods.0', 'cash')
            ->assertJsonPath('data.blocked_payment_methods.0', 'pix');

        $products = collect($response->json('data.products'));
        $this->assertCount(1, $products);
        $this->assertSame($available->uuid, $products->first()['uuid']);
        $this->assertEqualsWithDelta(8, (float) $products->first()['stock_quantity'], 0.001);
    }

    #[Test]
    public function closing_session_computes_expected_amount_with_cash_sale_supply_and_withdrawal(): void
    {
        $this->grantAllPdvPermissions();
        $session = $this->openSession(100.0);
        $sessionUuid = $session['uuid'];

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        // Venda paga só em dinheiro: 3 x 10 = 30 (entra no esperado).
        $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 30]],
        ])->assertStatus(201);

        // Suprimento +50, sangria -20.
        $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$sessionUuid}/movements", [
            'type' => 'supply',
            'amount' => 50,
        ])->assertStatus(201);

        $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$sessionUuid}/movements", [
            'type' => 'withdrawal',
            'amount' => 20,
        ])->assertStatus(201);

        // Esperado = 100 (abertura) + 30 (dinheiro) + 50 (suprimento) - 20 (sangria) = 160.
        $response = $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$sessionUuid}/close", [
            'closing_amount_declared' => 155,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.closing_amount_expected', 160)
            ->assertJsonPath('data.closing_amount_declared', 155)
            // Divergência registrada, NÃO bloqueia o fechamento: 155 - 160 = -5.
            ->assertJsonPath('data.difference', -5);
    }

    #[Test]
    public function cannot_open_two_sessions_for_the_same_register(): void
    {
        $this->grantAllPdvPermissions();
        $this->openSession();

        $response = $this->auth()->postJson('/api/v1/pdv/cash-sessions', [
            'opening_amount' => 50,
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'CASH_SESSION_ERROR');
    }

    #[Test]
    public function open_close_and_sale_generate_audit_logs(): void
    {
        $this->grantAllPdvPermissions();
        $session = $this->openSession();

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 50);

        $this->auth()->postJson('/api/v1/pdv/sales', [
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(201);

        $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$session['uuid']}/close", [
            'closing_amount_declared' => 110,
        ])->assertStatus(200);

        $this->assertSame(1, AuditLog::where('event', 'cash_session_opened')->count());
        $this->assertSame(1, AuditLog::where('event', 'pdv_sale_completed')->count());
        $this->assertSame(1, AuditLog::where('event', 'cash_session_closed')->count());
    }

    #[Test]
    public function cash_sessions_are_isolated_between_tenants(): void
    {
        $this->grantAllPdvPermissions();

        // Sessão de OUTRO tenant, criada diretamente.
        $foreignTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Foreign Tenant',
            'slug' => 'foreign-' . Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $foreignRegister = CashRegister::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'name' => 'Caixa Alheio',
            'is_active' => true,
        ]);

        $foreignSession = CashSession::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'cash_register_id' => $foreignRegister->id,
            'opened_at' => now(),
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        // Lista do tenant A não enxerga a sessão de B.
        $list = $this->auth()->getJson('/api/v1/pdv/cash-sessions')->assertStatus(200)->json('data');
        $this->assertCount(0, $list);

        // current do tenant A é null (A não tem sessão aberta).
        $this->auth()->getJson('/api/v1/pdv/cash-sessions/current')
            ->assertStatus(200)
            ->assertJsonPath('data', null);

        // Movimentar/fechar a sessão de B pelo tenant A -> 404 (IDOR barrado).
        $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$foreignSession->uuid}/movements", [
            'type' => 'supply',
            'amount' => 10,
        ])->assertStatus(404);

        $this->auth()->postJson("/api/v1/pdv/cash-sessions/{$foreignSession->uuid}/close", [
            'closing_amount_declared' => 100,
        ])->assertStatus(404);

        // Vender apontando pra sessão de B -> validação de escopo rejeita (422).
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 10]);
        $this->stockEntry($this->tenant->id, $product, $location, 10);

        $this->auth()->postJson('/api/v1/pdv/sales', [
            'cash_session_uuid' => $foreignSession->uuid,
            'stock_location_uuid' => $location->uuid,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(422);
    }
}
