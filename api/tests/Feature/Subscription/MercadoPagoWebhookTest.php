<?php

namespace Tests\Feature\Subscription;

use App\Models\Finance\Receivable;
use App\Models\Finance\Settlement;
use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

/**
 * Webhook do Mercado Pago (roadmap Fase B, item 1; migrado para a API de
 * Orders). Cobre validação da assinatura x-signature (HMAC-SHA256), rejeição
 * sem assinatura válida, idempotência (mesmo evento 2x não reprocessa) e
 * reconciliação do pagamento de uma venda via GET /v1/orders/{id} (mockado).
 * `data.id` agora é o id alfanumérico da order (ex. "ORD01JQ...").
 */
class MercadoPagoWebhookTest extends TestCase
{
    use CreatesSaleFixtures;
    use CreatesSubscriptionFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    private const SECRET = 'whsec_test_123';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.webhook_secret', self::SECRET);

        $this->setUpTenantScopedUser('mp-webhook-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function signedHeaders(string $dataId, string $requestId, ?int $ts = null): array
    {
        $ts ??= time();
        // O provider (MercadoPagoPaymentProvider::validateWebhook) lowercasa
        // data.id antes de montar o manifest — os ids de order são
        // alfanuméricos (ex. "ORD01JQ..."), diferente dos ids de payment
        // (só numéricos), então o manifest de teste precisa reproduzir o
        // mesmo lowercase para a assinatura bater.
        $manifest = 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, self::SECRET);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$ts},v1={$v1}",
        ];
    }

    private function makeOrderPayment(): array
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => 75.5]);

        $orderData = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => 1],
            ],
        ])->json('data');

        $order = Sale::where('uuid', $orderData['uuid'])->firstOrFail();

        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $order->id,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
            'method' => 'pix',
            'amount' => 75.5,
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$order, $payment];
    }

    private function createReceivableForOrder(
        Sale $order,
        float $grossAmount,
        float $platformFeeAmount,
        float $netAmount,
        string $status = 'awaiting_release',
        ?Settlement $settlement = null,
    ): Receivable {
        $receivable = Receivable::query()->where('sale_id', $order->id)->first();

        if ($receivable === null) {
            return Receivable::create([
                'tenant_id' => $order->tenant_id,
                'sale_id' => $order->id,
                'event_id' => null,
                'settlement_id' => $settlement?->id,
                'status' => $status,
                'currency' => 'BRL',
                'gross_amount' => number_format($grossAmount, 2, '.', ''),
                'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
                'processor_fee_amount' => '0.00',
                'net_amount' => number_format($netAmount, 2, '.', ''),
                'settlement_reference' => 'event_end_d_plus_1',
                'event_ends_at' => now()->subDay(),
                'available_at' => now()->subHour(),
                'provider' => 'mercadopago',
                'provider_charge_id' => 'ORD_MP_RECEIVABLE',
                'provider_split_id' => null,
            ]);
        }

        $receivable->fill([
            'settlement_id' => $settlement?->id,
            'status' => $status,
            'gross_amount' => number_format($grossAmount, 2, '.', ''),
            'platform_fee_amount' => number_format($platformFeeAmount, 2, '.', ''),
            'processor_fee_amount' => '0.00',
            'net_amount' => number_format($netAmount, 2, '.', ''),
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD_MP_RECEIVABLE',
            'provider_split_id' => null,
        ]);
        $receivable->save();

        return $receivable->fresh();
    }

    /**
     * @return array{0: Invoice, 1: Payment}
     */
    private function makeInvoicePayment(): array
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id]);

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_gross' => 99.90,
            'discount_amount' => 0,
            'amount_net' => 99.90,
            'status' => 'paid',
        ]);

        $payment = Payment::create([
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'MP_CYCLE_1',
            'method' => 'card',
            'amount' => 99.90,
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => ['transaction_id' => 'txn_invoice_cycle_1'],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return [$invoice, $payment];
    }

    #[Test]
    public function rejects_webhook_without_a_valid_signature(): void
    {
        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_bad_sig',
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ], [
            'x-request-id' => (string) Str::uuid(),
            'x-signature' => 'ts='.time().',v1=deadbeef',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'WEBHOOK_INVALID_SIGNATURE');
    }

    #[Test]
    public function rejects_webhook_with_no_signature_header_at_all(): void
    {
        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_no_sig',
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ])->assertStatus(401);
    }

    #[Test]
    public function invalid_signature_never_persists_a_webhook_event_row(): void
    {
        // security-standards.md, regra 8: assinatura inválida não pode
        // gravar nada em webhook_events — senão um external_id forjado
        // "reservaria" a idempotência antes do evento legítimo chegar.
        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_forged',
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ], [
            'x-request-id' => (string) Str::uuid(),
            'x-signature' => 'ts='.time().',v1=deadbeef',
        ])->assertStatus(401);

        $this->assertSame(
            0,
            WebhookEvent::where('provider', 'mercadopago')->where('external_id', 'evt_forged')->count()
        );
    }

    #[Test]
    public function rejects_sandbox_live_mode_false_event_when_running_in_production(): void
    {
        $this->app['env'] = 'production';

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('ORD01JQ4S4KY8HWQ6NA5PXB65B3D3', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_sandbox_in_prod',
            'live_mode' => false,
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ], $headers)->assertStatus(401);

        $this->assertSame(
            0,
            WebhookEvent::where('provider', 'mercadopago')->where('external_id', 'evt_sandbox_in_prod')->count()
        );
    }

    #[Test]
    public function accepts_a_validly_signed_webhook_and_reconciles_the_order_payment(): void
    {
        [$order, $payment] = $this->makeOrderPayment();

        Http::fake([
            'api.mercadopago.com/v1/orders/ORD01JQ4S4KY8HWQ6NA5PXB65B3D3' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'processed',
                'total_amount' => '75.50',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_555000111',
                        'status' => 'approved',
                    ]],
                ],
            ], 200),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('ORD01JQ4S4KY8HWQ6NA5PXB65B3D3', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_ok_1',
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ], $headers)->assertStatus(200);

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertTrue((bool) $order->is_paid);
    }

    #[Test]
    public function duplicate_webhook_delivery_does_not_reconcile_twice(): void
    {
        [, $payment] = $this->makeOrderPayment();

        Http::fake([
            'api.mercadopago.com/v1/orders/ORD01JQ4S4KY8HWQ6NA5PXB65B3D3' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'processed',
                'total_amount' => '75.50',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_555000111',
                        'status' => 'approved',
                    ]],
                ],
            ], 200),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('ORD01JQ4S4KY8HWQ6NA5PXB65B3D3', $requestId);
        $payload = ['id' => 'evt_dup_mp', 'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3']];

        $this->postJson('/api/v1/webhooks/payments/mercadopago', $payload, $headers)->assertStatus(200);
        $this->postJson('/api/v1/webhooks/payments/mercadopago', $payload, $headers)->assertStatus(200);

        $this->assertSame(1, WebhookEvent::where('provider', 'mercadopago')->where('external_id', 'evt_dup_mp')->count());
        Http::assertSentCount(1);
    }

    #[Test]
    public function valid_subscription_preapproval_webhook_cancels_the_local_subscription_when_remote_is_cancelled(): void
    {
        $plan = $this->createPlan();
        $planPrice = $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $planPrice,
            'status' => 'active',
            'billing_period' => 'monthly',
            'preapproval_id' => 'preapproval_cancel_test',
            'auto_renew' => true,
        ]);

        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_cancel_test' => Http::response([
                'id' => 'preapproval_cancel_test',
                'status' => 'cancelled',
            ], 200),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('preapproval_cancel_test', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_preapproval_cancel_1',
            'type' => 'subscription_preapproval',
            'data' => ['id' => 'preapproval_cancel_test'],
        ], $headers)->assertStatus(200);

        $subscription->refresh();

        $this->assertSame('canceled', $subscription->status);
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertNotNull($subscription->canceled_at);
    }

    #[Test]
    public function valid_chargeback_webhook_flags_the_order_payment_for_review_and_creates_a_refund_record(): void
    {
        [$order, $payment] = $this->makeOrderPayment();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => ['transaction_id' => 'txn_chargeback_1'],
        ]);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-CHB-OPEN',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
            'gross_amount' => 75.50,
            'platform_fee_amount' => 10.00,
            'processor_fee_amount' => 0,
            'net_amount' => 65.50,
        ]);

        $receivable = $this->createReceivableForOrder($order, 75.50, 10.00, 65.50, 'awaiting_release', $settlement);

        Http::fake([
            'api.mercadopago.com/v1/chargebacks/chb_1' => Http::response([
                'id' => 'chb_1',
                'payments' => ['txn_chargeback_1'],
                'amount' => '75.50',
                'currency' => 'BRL',
            ], 200),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('chb_1', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_chargeback_1',
            'type' => 'topic_chargebacks_wh',
            'data' => ['id' => 'chb_1'],
        ], $headers)->assertStatus(200);

        $payment->refresh();

        $this->assertSame('divergent', $payment->status);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'provider_refund_id' => 'chb_1',
            'status' => 'requested',
        ]);
        $this->assertDatabaseHas('settlement_adjustments', [
            'receivable_id' => $receivable->id,
            'type' => 'chargeback_organizer_exposure',
            'status' => 'applied',
            'amount' => 65.50,
        ]);
        $this->assertDatabaseHas('settlement_adjustments', [
            'receivable_id' => $receivable->id,
            'type' => 'chargeback_platform_exposure',
            'status' => 'pending_review',
            'amount' => 10.00,
        ]);
    }

    /**
     * Achado de auditoria: a idempotência original era só (provider,
     * external_id). O Mercado Pago usa `data.id`/`id` dentro do escopo de
     * cada TIPO de recurso — nada garante que dois tipos diferentes nunca
     * coincidam de id. Sem `type` na chave, o segundo evento legítimo
     * (tipo diferente, mesmo id por coincidência) seria descartado como
     * "já processado" e o pagamento correspondente NUNCA seria marcado
     * para revisão — perda real de notificação financeira.
     */
    #[Test]
    public function webhook_events_with_the_same_external_id_but_different_types_are_both_processed(): void
    {
        [, $paymentA] = $this->makeOrderPayment();
        $paymentA->update([
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => ['transaction_id' => 'txn_collision_a'],
        ]);

        [, $paymentB] = $this->makeOrderPayment();
        $paymentB->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_charge_id' => 'ORD_COLLISION_B',
        ]);

        Http::fake([
            'api.mercadopago.com/v1/chargebacks/chb_shared' => Http::response([
                'id' => 'chb_shared',
                'payments' => ['txn_collision_a'],
                'amount' => '75.50',
                'currency' => 'BRL',
            ], 200),
        ]);

        // Mesmo `id` de topo ("evt_shared_1") usado nas duas entregas —
        // só o `type` diferencia os dois eventos.
        $firstHeaders = $this->signedHeaders('chb_shared', (string) Str::uuid());
        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_shared_1',
            'type' => 'topic_chargebacks_wh',
            'data' => ['id' => 'chb_shared'],
        ], $firstHeaders)->assertStatus(200);

        $secondHeaders = $this->signedHeaders('ORD_COLLISION_B', (string) Str::uuid());
        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_shared_1',
            'type' => 'delivery_cancellation',
            'data' => ['id' => 'ORD_COLLISION_B'],
        ], $secondHeaders)->assertStatus(200);

        $paymentA->refresh();
        $paymentB->refresh();

        $this->assertSame('divergent', $paymentA->status);
        $this->assertSame('divergent', $paymentB->status);

        $this->assertDatabaseHas('refunds', ['payment_id' => $paymentA->id, 'provider_refund_id' => 'chb_shared']);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $paymentB->id,
            'provider_refund_id' => 'fraud-alert:ORD_COLLISION_B',
        ]);

        $this->assertSame(2, WebhookEvent::where('provider', 'mercadopago')->where('external_id', 'evt_shared_1')->count());
    }

    #[Test]
    public function fraud_alert_webhook_flags_the_order_payment_for_review(): void
    {
        [$order, $payment] = $this->makeOrderPayment();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $settlement = Settlement::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SET-CHB-RELEASED',
            'status' => 'released',
            'scheduled_for' => now()->subDay(),
            'released_at' => now()->subHours(3),
            'gross_amount' => 75.50,
            'platform_fee_amount' => 70.00,
            'processor_fee_amount' => 0,
            'net_amount' => 5.50,
        ]);

        $receivable = $this->createReceivableForOrder($order, 75.50, 70.00, 5.50, 'released', $settlement);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('ORD01JQ4S4KY8HWQ6NA5PXB65B3D3', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_fraud_1',
            'type' => 'delivery_cancellation',
            'data' => ['id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3'],
        ], $headers)->assertStatus(200);

        $payment->refresh();

        $this->assertSame('divergent', $payment->status);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'provider_refund_id' => 'fraud-alert:ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
            'status' => 'requested',
        ]);
        $this->assertDatabaseHas('settlement_adjustments', [
            'receivable_id' => $receivable->id,
            'type' => 'chargeback_organizer_exposure',
            'status' => 'pending_recovery',
            'amount' => 5.50,
        ]);
        $this->assertDatabaseHas('settlement_adjustments', [
            'receivable_id' => $receivable->id,
            'type' => 'chargeback_platform_exposure',
            'status' => 'pending_review',
            'amount' => 70.00,
        ]);
    }

    #[Test]
    public function chargeback_webhook_flags_a_subscription_invoice_payment_as_disputed(): void
    {
        [$invoice, $payment] = $this->makeInvoicePayment();

        Http::fake([
            'api.mercadopago.com/v1/chargebacks/chb_invoice_1' => Http::response([
                'id' => 'chb_invoice_1',
                'payments' => ['txn_invoice_cycle_1'],
                'amount' => '99.90',
                'currency' => 'BRL',
                'resolution_date' => '2026-08-15T00:00:00.000-04:00',
            ], 200),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('chb_invoice_1', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_chargeback_invoice_1',
            'type' => 'topic_chargebacks_wh',
            'data' => ['id' => 'chb_invoice_1'],
        ], $headers)->assertStatus(200);

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame('divergent', $payment->status);
        $this->assertSame('disputed', $invoice->status);
        $this->assertNotNull($invoice->dispute_deadline_at);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'provider_refund_id' => 'chb_invoice_1',
            'status' => 'requested',
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $invoice->subscription_id,
            'type' => 'invoice_disputed',
        ]);
    }

    #[Test]
    public function webhook_returns_a_handled_error_when_the_provider_call_fails_and_keeps_the_event_unprocessed_for_retry(): void
    {
        [, $payment] = $this->makeOrderPayment();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'metadata' => ['transaction_id' => 'txn_provider_failure_1'],
        ]);

        Http::fake([
            'api.mercadopago.com/v1/chargebacks/chb_failure_1' => Http::response(['message' => 'internal error'], 500),
        ]);

        $requestId = (string) Str::uuid();
        $headers = $this->signedHeaders('chb_failure_1', $requestId);

        $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => 'evt_provider_failure_1',
            'type' => 'topic_chargebacks_wh',
            'data' => ['id' => 'chb_failure_1'],
        ], $headers)
            ->assertStatus(502)
            ->assertJsonPath('code', 'WEBHOOK_PROCESSING_FAILED');

        $event = WebhookEvent::where('provider', 'mercadopago')
            ->where('type', 'topic_chargebacks_wh')
            ->where('external_id', 'evt_provider_failure_1')
            ->first();

        $this->assertNotNull($event);
        $this->assertNull($event->processed_at);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }
}
