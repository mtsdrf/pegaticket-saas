<?php

namespace Tests\Feature\Subscription;

use App\Console\Commands\ReconcilePaymentIdempotencyCommand;
use App\Models\Payment\PaymentIdempotencyKey;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Idempotência persistida de cobrança Mercado Pago (roadmap Fase B —
 * risco ALTO fechado em 2026-07-24): garante que um timeout/erro de rede
 * na criação nunca gera uma cobrança duplicada porque o retry reutiliza a
 * mesma chave (ou é bloqueado), nunca gera uma X-Idempotency-Key nova.
 */
class PaymentIdempotencyTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.payments.sale_provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.webhook_secret', 'fake-secret');
        Config::set('services.mercadopago.idempotency_lock_seconds', 120);

        $this->setUpTenantScopedUser('idempotency-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createConfirmedOrder(float $price = 40): array
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        // Venda manual não parcelada nasce já paga — desfaz aqui pra
        // simular uma venda ainda não paga (pré-condição de
        // createPixChargeForOrder()).
        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->json('data');

        Sale::where('uuid', $order['uuid'])->update(['is_paid' => false, 'paid_at' => null]);

        return $order;
    }

    #[Test]
    public function a_network_timeout_never_generates_a_new_key_and_a_fast_retry_is_blocked(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder();

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertSame(0, Payment::where('payable_type', Sale::class)->where('payable_id', $saleId)->count());

        $record = PaymentIdempotencyKey::where('operation', "order_charge:{$order['uuid']}")->firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->assertNotNull($record->locked_until);
        $this->assertTrue($record->locked_until->isFuture());
        $firstKey = $record->idempotency_key;

        // Retry rápido (dentro do lock): bloqueado explicitamente, o
        // Mercado Pago NUNCA é chamado de novo.
        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_OPERATION_IN_PROGRESS');

        $this->assertSame($firstKey, $record->fresh()->idempotency_key);
        $this->assertSame(0, Payment::where('payable_type', Sale::class)->where('payable_id', $saleId)->count());
    }

    #[Test]
    public function a_clear_validation_error_allows_an_immediate_retry_with_a_new_key(): void
    {
        $orderRef = null;

        Http::fake(function ($request) use (&$orderRef) {
            $orderRef ??= $request['external_reference'] ?? null;

            static $calls = 0;
            $calls++;

            if ($calls === 1) {
                return Http::response(['message' => 'invalid card data'], 400);
            }

            return Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'transactions' => ['payments' => [['id' => 'txn_1', 'status' => 'pending', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
            ], 201);
        });

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder();

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE');

        $record = PaymentIdempotencyKey::where('operation', "order_charge:{$order['uuid']}")->firstOrFail();
        $this->assertSame('failed', $record->status);
        $firstKey = $record->idempotency_key;

        // Retry imediato: como a falha foi DECISIVA (dado inválido), uma
        // chave nova é gerada e a chamada ao PSP é permitida de novo.
        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(201);

        $this->assertNotSame($firstKey, $record->fresh()->idempotency_key);
        $this->assertSame('succeeded', $record->fresh()->status);
        $this->assertSame(2, Http::recorded()->count());

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertSame(1, Payment::where('payable_type', Sale::class)->where('payable_id', $saleId)->count());
    }

    #[Test]
    public function reconciliation_resolves_an_expired_ambiguous_lock_by_creating_the_missing_local_payment(): void
    {
        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 55);
        $orderModel = Sale::where('uuid', $order['uuid'])->firstOrFail();

        // Simula o timeout ambíguo já resolvido no PSP (o MP processou,
        // mas nossa resposta nunca chegou) com o lock já expirado, sem
        // reconciliação ainda ter rodado.
        $record = PaymentIdempotencyKey::create([
            'tenant_id' => $this->tenant->id,
            'operation' => "order_charge:{$order['uuid']}",
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending',
            'locked_until' => now()->subMinutes(5),
        ]);

        Http::fake([
            'api.mercadopago.com/v1/orders/search*' => Http::response([
                'elements' => [[
                    'id' => 'ORD_RECONCILED_1',
                    'status' => 'processed',
                    'total_amount' => '55.00',
                    'transactions' => ['payments' => [['status' => 'approved']]],
                ]],
            ], 200),
        ]);

        $this->artisan(ReconcilePaymentIdempotencyCommand::class)->assertExitCode(0);

        $record->refresh();
        $this->assertSame('succeeded', $record->status);

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $orderModel->id,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD_RECONCILED_1',
            'idempotency_key' => $record->idempotency_key,
        ]);
    }
}
