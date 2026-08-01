<?php

namespace Tests\Feature\Console;

use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

class ReconcileMercadoPagoSalePaymentsCommandTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesOrderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);
        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');

        $this->setUpTenantScopedUser('reconcile-mp-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createOrderWithPayment(string $paymentStatus = 'pending', string $amount = '75.50'): array
    {
        $this->grantPermission('sales', 'create');

        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => (float) $amount]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sales', [
                'final_customer_uuid' => $client->uuid,
                'stock_location_uuid' => $location->uuid,
                'is_installment' => false,
                'items' => [
                    ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
                ],
            ])
            ->assertStatus(201);

        $order = Sale::where('uuid', $response->json('data.uuid'))->firstOrFail();

        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD-' . Str::upper(Str::random(16)),
            'method' => 'pix',
            'amount' => $amount,
            'status' => $paymentStatus,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$order, $payment];
    }

    #[Test]
    public function command_marks_pending_payment_as_paid_when_remote_order_is_paid(): void
    {
        [$order, $payment] = $this->createOrderWithPayment('pending', '75.50');

        Http::fake([
            "api.mercadopago.com/v1/orders/{$payment->provider_charge_id}" => Http::response([
                'id' => $payment->provider_charge_id,
                'status' => 'processed',
                'total_amount' => '75.50',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_ok',
                        'status' => 'approved',
                    ]],
                ],
            ], 200),
        ]);

        Artisan::call('payments:reconcile-mercadopago-orders', ['--payment_uuid' => $payment->uuid]);

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertTrue((bool) $order->is_paid);
    }

    #[Test]
    public function command_recovers_a_divergent_payment_when_remote_amount_matches_expected(): void
    {
        [$order, $payment] = $this->createOrderWithPayment('divergent', '120.00');

        Http::fake([
            "api.mercadopago.com/v1/orders/{$payment->provider_charge_id}" => Http::response([
                'id' => $payment->provider_charge_id,
                'status' => 'processed',
                'total_amount' => '120.00',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_recovered',
                        'status' => 'approved',
                    ]],
                ],
            ], 200),
        ]);

        Artisan::call('payments:reconcile-mercadopago-orders', ['--payment_uuid' => $payment->uuid]);

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertTrue((bool) $order->is_paid);
    }

    #[Test]
    public function command_marks_non_paid_remote_order_as_failed_when_local_charge_is_not_settled(): void
    {
        [, $payment] = $this->createOrderWithPayment('pending', '55.00');

        Http::fake([
            "api.mercadopago.com/v1/orders/{$payment->provider_charge_id}" => Http::response([
                'id' => $payment->provider_charge_id,
                'status' => 'cancelled',
                'total_amount' => '55.00',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_failed',
                        'status' => 'rejected',
                    ]],
                ],
            ], 200),
        ]);

        Artisan::call('payments:reconcile-mercadopago-orders', ['--payment_uuid' => $payment->uuid]);

        $payment->refresh();

        $this->assertSame('failed', $payment->status);
    }

    /**
     * Achado (2026-07-24): uma falha de consulta ao Mercado Pago para UM
     * pagamento (ex.: 5xx/timeout transitório) não pode impedir os DEMAIS
     * itens da mesma rodada de reconciliação de serem processados — o
     * try/catch por iteração isola a falha (ver
     * ReconcileMercadoPagoOrderPaymentsCommand::handle()).
     */
    #[Test]
    public function a_single_failing_payment_does_not_block_the_others_in_the_same_run(): void
    {
        [, $failingPayment] = $this->createOrderWithPayment('pending', '10.00');
        [$order, $okPayment] = $this->createOrderWithPayment('pending', '20.00');

        Http::fake([
            "api.mercadopago.com/v1/orders/{$failingPayment->provider_charge_id}" => Http::response(['message' => 'boom'], 500),
            "api.mercadopago.com/v1/orders/{$okPayment->provider_charge_id}" => Http::response([
                'id' => $okPayment->provider_charge_id,
                'status' => 'processed',
                'total_amount' => '20.00',
                'transactions' => [
                    'payments' => [['id' => 'txn_ok_isolated', 'status' => 'approved']],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('payments:reconcile-mercadopago-orders', ['--limit' => 10]);

        $failingPayment->refresh();
        $okPayment->refresh();
        $order->refresh();

        $this->assertNotSame(0, $exitCode);
        $this->assertSame('pending', $failingPayment->status);
        $this->assertSame('paid', $okPayment->status);
        $this->assertTrue((bool) $order->is_paid);
    }
}
