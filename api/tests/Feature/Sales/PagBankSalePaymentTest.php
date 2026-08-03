<?php

namespace Tests\Feature\Sales;

use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\WebhookEvent;
use App\Services\Sale\SalePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Cobrança Pix de pedido via PagBankPaymentProvider (integração real contra
 * a API de Pedidos do PagBank, confirmada via documentação oficial
 * developer.pagbank.com.br). Client HTTP mockado (Http::fake) — nenhuma
 * chamada de rede real acontece em teste.
 */
class PagBankSalePaymentTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);
        Config::set('services.payments.sale_provider', 'pagbank');
        Config::set('services.pagbank.environment', 'sandbox');
        Config::set('services.pagbank.token', 'fake-pagbank-token');

        $this->setUpTenantScopedUser('pagbank-order-payment-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function createConfirmedOrder(float $price = 40, int $qty = 1): array
    {
        $this->grantPermission('sales', 'create');
        $client = $this->createClient($this->tenant->id);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => $qty],
            ],
        ])->json('data');

        // Venda manual não parcelada nasce já paga — desfaz aqui pra
        // simular uma venda ainda não paga (pré-condição de
        // createPixChargeForOrder()).
        Sale::where('uuid', $order['uuid'])->update(['is_paid' => false, 'paid_at' => null]);

        return $order;
    }

    #[Test]
    public function creates_a_pix_order_via_pagbank_and_stores_the_qr_code(): void
    {
        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328',
                'reference_id' => 'sale-ref',
                'qr_codes' => [[
                    'id' => 'QRCO_1',
                    'text' => '00020126...copia-e-cola...6304ABCD',
                    'expiration_date' => '2026-08-03T12:00:00-03:00',
                ]],
            ], 201),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge");

        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328',
            'status' => 'pending',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/orders'
                && $request->hasHeader('Authorization', 'Bearer fake-pagbank-token')
                && $request['qr_codes'][0]['amount']['value'] === 8000
                && $request['notification_urls'][0] === url('/api/v1/webhooks/payments/pagbank');
        });
    }

    #[Test]
    public function cancelling_a_paid_order_requests_a_real_refund_in_pagbank(): void
    {
        $orderId = 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328';

        Http::fake([
            'sandbox.api.pagseguro.com/orders' => Http::response([
                'id' => $orderId,
                'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020126...']],
            ], 201),
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_1',
                    'status' => 'PAID',
                    'amount' => ['value' => 5000, 'currency' => 'BRL'],
                ]],
            ], 200),
            'sandbox.api.pagseguro.com/charges/CHAR_1/cancel' => Http::response([
                'id' => 'CHAR_1',
                'status' => 'CANCELED',
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'cancel');
        $order = $this->createConfirmedOrder(price: 50, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $orderModel = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $payment = $orderModel->payments()->firstOrFail();

        app(SalePaymentService::class)->reconcileWebhookPayment($payment, '50.00');

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu após pagar',
        ])->assertStatus(200);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'status' => 'refunded',
            'amount' => '50.00',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.api.pagseguro.com/charges/CHAR_1/cancel';
        });
    }

    #[Test]
    public function webhook_with_invalid_signature_is_rejected(): void
    {
        $payload = ['id' => 'ORDE_ABC', 'charges' => [['status' => 'PAID']]];

        $response = $this->postJson('/api/v1/webhooks/payments/pagbank', $payload, [
            'x-authenticity-token' => 'wrong-signature',
        ]);

        $response->assertStatus(401)->assertJsonPath('code', 'WEBHOOK_INVALID_SIGNATURE');
        $this->assertSame(0, WebhookEvent::where('provider', 'pagbank')->count());
    }

    #[Test]
    public function webhook_with_valid_signature_reconciles_the_payment_after_reconsulting_pagbank(): void
    {
        $orderId = 'ORDE_F87334AC-BB8B-42E2-AA85-8579F70AA328';

        Http::fake([
            "sandbox.api.pagseguro.com/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'charges' => [[
                    'id' => 'CHAR_1',
                    'status' => 'PAID',
                    'amount' => ['value' => 8000, 'currency' => 'BRL'],
                ]],
            ], 200),
        ]);

        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);
        $saleId = Sale::where('uuid', $order['uuid'])->value('id');

        Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'provider' => 'pagbank',
            'provider_charge_id' => $orderId,
            'method' => 'pix',
            'amount' => '80.00',
            'status' => 'pending',
        ]);

        $rawPayload = json_encode(['id' => $orderId, 'charges' => [['status' => 'PAID']]]);
        $signature = hash('sha256', 'fake-pagbank-token-'.$rawPayload);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/payments/pagbank',
            [],
            [],
            [],
            [
                'HTTP_x-authenticity-token' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'is_paid' => true]);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'pagbank',
            'external_id' => $orderId,
        ]);
    }
}
