<?php

namespace Tests\Feature\Orders;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Order\Order;
use App\Models\Subscription\Payment;
use App\Services\Order\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Orders\Concerns\CreatesOrderFixtures;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\TestCase;

/**
 * Cobrança Pix de pedido via MercadoPagoPaymentProvider (roadmap Fase B,
 * item 1 — PSP real). O client HTTP do Mercado Pago é mockado (Http::fake)
 * — nenhuma chamada de rede real acontece em teste.
 */
class MercadoPagoOrderPaymentTest extends TestCase
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
        Config::set('services.mercadopago.webhook_secret', 'fake-secret');

        $this->setUpTenantScopedUser('mp-order-payment-user@test.com');
    }

    protected function auth()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function createConfirmedOrder(float $price = 40, int $qty = 1): array
    {
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => $price]);

        $this->stockEntry($this->tenant->id, $product, $location, 100);

        return $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [
                ['product_uuid' => $product->uuid, 'quantity' => $qty],
            ],
        ])->json('data');
    }

    #[Test]
    public function creates_a_pix_charge_via_mercadopago_and_stores_the_qr_code(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'status_detail' => 'waiting_transfer',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_987654321',
                        'status' => 'pending',
                        'status_detail' => 'pending_waiting_transfer',
                        'payment_method' => [
                            'id' => 'pix',
                            'type' => 'bank_transfer',
                            'qr_code' => '00020126...copia-e-cola...6304ABCD',
                            'qr_code_base64' => 'aGVsbG8=',
                            'ticket_url' => 'https://www.mercadopago.com/payments/987654321/ticket',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $this->grantPermission('orders', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        $response = $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.metadata.qr_code', '00020126...copia-e-cola...6304ABCD');

        $orderId = Order::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Order::class,
            'payable_id' => $orderId,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
            'status' => 'pending',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders'
                && $request->hasHeader('Authorization', 'Bearer TEST-fake-token')
                && $request->hasHeader('X-Idempotency-Key')
                && $request['type'] === 'online'
                && $request['processing_mode'] === 'automatic'
                && $request['transactions']['payments'][0]['payment_method']['id'] === 'pix';
        });
    }

    #[Test]
    public function does_not_persist_a_payment_when_mercadopago_rejects_the_request(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'message' => 'invalid access token',
            ], 401),
        ]);

        $this->grantPermission('orders', 'update');
        $order = $this->createConfirmedOrder();

        // Falha do Mercado Pago nunca deve vazar como 500 genérico para o
        // proprietário/cliente final — vira 422 com mensagem amigável
        // (PaymentProviderException capturada no Controller).
        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE');

        $orderId = Order::where('uuid', $order['uuid'])->value('id');
        $this->assertSame(0, Payment::where('payable_type', Order::class)->where('payable_id', $orderId)->count());
    }

    #[Test]
    public function cancelling_a_paid_order_requests_a_real_refund_in_mercadopago(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'transactions' => [
                    'payments' => [[
                        'id' => 'txn_987654321',
                        'status' => 'pending',
                        'payment_method' => [
                            'id' => 'pix',
                            'type' => 'bank_transfer',
                            'qr_code' => '00020126...copia-e-cola...6304ABCD',
                        ],
                    ]],
                ],
            ], 201),
            'api.mercadopago.com/v1/orders/ORD01JQ4S4KY8HWQ6NA5PXB65B3D3/refund' => Http::response([
                'id' => 'refund_mp_1',
            ], 201),
        ]);

        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'cancel');
        $order = $this->createConfirmedOrder(price: 50, qty: 1);

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge")->assertStatus(201);

        $orderModel = Order::where('uuid', $order['uuid'])->firstOrFail();
        $payment = $orderModel->payments()->firstOrFail();

        app(OrderPaymentService::class)->reconcileWebhookPayment($payment, '50.00');

        $this->auth()->patchJson("/api/v1/orders/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu após pagar',
        ])->assertStatus(200);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'provider_refund_id' => 'refund_mp_1',
            'status' => 'requested',
            'amount' => '50.00',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders/ORD01JQ4S4KY8HWQ6NA5PXB65B3D3/refund'
                && $request->hasHeader('Authorization', 'Bearer TEST-fake-token')
                && $request->hasHeader('X-Idempotency-Key');
        });
    }

    /**
     * Bug real confirmado contra a API real do Mercado Pago: POST /v1/orders
     * exige `payer` com ao menos 1 propriedade preenchida. `clients` (cadastro
     * do staff) não tem coluna e-mail; só `final_customers` (login do Portal)
     * tem, ligada via `final_customer_tenant_links`.
     */
    #[Test]
    public function pix_charge_uses_the_linked_final_customers_email_as_payer(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'transactions' => ['payments' => [['id' => 'txn_1', 'status' => 'pending', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
            ], 201),
        ]);

        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);

        $finalCustomer = FinalCustomer::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Cliente Final',
            'email' => 'cliente.final@example.com',
        ]);

        FinalCustomerTenantLink::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'final_customer_id' => $finalCustomer->id,
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'confirmed_at' => now(),
        ]);

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);
        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
        ])->json('data');

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge")->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders'
                && $request['payer']['email'] === 'cliente.final@example.com';
        });
    }

    #[Test]
    public function pix_charge_falls_back_to_client_document_when_no_final_customer_is_linked(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'transactions' => ['payments' => [['id' => 'txn_1', 'status' => 'pending', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
            ], 201),
        ]);

        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $client->update(['cpf_cnpj' => '12345678901']);

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);
        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
        ])->json('data');

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge")->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders'
                && ($request['payer']['identification']['type'] ?? null) === 'CPF'
                && ($request['payer']['identification']['number'] ?? null) === '12345678901'
                && !isset($request['payer']['email']);
        });
    }

    #[Test]
    public function pix_charge_falls_back_to_the_clients_real_name_when_no_email_or_document_is_available(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD01JQ4S4KY8HWQ6NA5PXB65B3D3',
                'status' => 'action_required',
                'transactions' => ['payments' => [['id' => 'txn_1', 'status' => 'pending', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
            ], 201),
        ]);

        $this->grantPermission('orders', 'update');
        $this->grantPermission('orders', 'create');
        $client = $this->createClient($this->tenant->id);
        $client->update(['name' => 'Maria Souza']);

        $location = $this->createLocation($this->tenant->id, ['is_default' => true]);
        $product = $this->createProduct($this->tenant->id, ['price' => 40]);
        $this->stockEntry($this->tenant->id, $product, $location, 100);

        $order = $this->auth()->postJson('/api/v1/orders', [
            'client_uuid' => $client->uuid,
            'stock_location_uuid' => $location->uuid,
            'is_installment' => false,
            'items' => [['product_uuid' => $product->uuid, 'quantity' => 1]],
        ])->json('data');

        $this->auth()->postJson("/api/v1/orders/{$order['uuid']}/payment-charge")->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders'
                && $request['payer']['first_name'] === 'Maria'
                && $request['payer']['last_name'] === 'Souza'
                && !isset($request['payer']['email'])
                && !isset($request['payer']['identification']);
        });
    }
}
