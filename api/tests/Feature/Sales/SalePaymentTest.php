<?php

namespace Tests\Feature\Sales;

use App\Enums\Payment\PaymentStatus;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Models\Tenant\Tenant;
use App\Services\Sale\SalePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Pagamento de PEDIDO (roadmap 2A) — recebimento do tenant via a tabela
 * polimórfica `payments` (payable=Sale) e o ManualPaymentProvider (no-op).
 * Cobre os casos especiais testáveis sem PSP real: duplicado, cancelado-pós-
 * pago (vira Refund), valor divergente e isolamento multi-tenant.
 */
class SalePaymentTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;
    use SetsUpTenantScopedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pegaticket.parcela_vencimento_dia', 10);
        Config::set('services.payments.sale_provider', 'manual');

        $this->setUpTenantScopedUser('order-payment-user@test.com');
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

        // Venda manual não parcelada nasce já paga — desfaz aqui pra
        // simular uma venda ainda não paga (pré-condição de
        // createPixChargeForOrder()), sem tocar em parcelamento.
        $order = $this->auth()->postJson('/api/v1/sales', [
            'final_customer_uuid' => $client->uuid,
            'is_installment' => false,
            'items' => [
                ['ticket_type_uuid' => $product->uuid, 'quantity' => $qty],
            ],
        ])->json('data');

        Sale::where('uuid', $order['uuid'])->update(['is_paid' => false, 'paid_at' => null]);

        return $order;
    }

    #[Test]
    public function creates_a_pix_charge_for_an_order(): void
    {
        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 40, qty: 2);

        $response = $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.amount', '80.00');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $saleId,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function does_not_allow_a_second_active_pix_charge_for_the_same_order(): void
    {
        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder();

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_ORDER_STATE');

        $saleId = Sale::where('uuid', $order['uuid'])->value('id');
        $this->assertSame(1, Payment::where('payable_type', Sale::class)->where('payable_id', $saleId)->count());
    }

    #[Test]
    public function cancelling_a_paid_order_creates_a_pending_refund_instead_of_deleting_the_payment(): void
    {
        $this->grantPermission('sales', 'update');
        $this->grantPermission('sales', 'cancel');
        $order = $this->createConfirmedOrder(price: 50, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        // Confirma o pagamento como se um webhook tivesse reportado o valor
        // correto (sem PSP real) — reconciliação direta pelo service.
        $orderModel = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $payment = $orderModel->payments()->first();

        app(SalePaymentService::class)->reconcileWebhookPayment($payment, 50.0);

        $this->assertTrue(Sale::where('uuid', $order['uuid'])->value('is_paid'));

        $this->auth()->patchJson("/api/v1/sales/{$order['uuid']}/cancel", [
            'cancellation_reason' => 'Cliente desistiu após pagar',
        ])->assertStatus(200);

        // Pagamento continua existindo (não foi apagado) e há um Refund
        // pendente amarrado a ele.
        $payment->refresh();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'status' => 'requested',
            'amount' => '50.00',
        ]);
        $this->assertSame(1, Refund::where('payment_id', $payment->id)->count());
    }

    #[Test]
    public function a_divergent_reported_amount_marks_the_charge_divergent_and_does_not_confirm(): void
    {
        $this->grantPermission('sales', 'update');
        $order = $this->createConfirmedOrder(price: 60, qty: 1);

        $this->auth()->postJson("/api/v1/sales/{$order['uuid']}/payment-charge")->assertStatus(201);

        $orderModel = Sale::where('uuid', $order['uuid'])->firstOrFail();
        $payment = $orderModel->payments()->first();

        // Webhook reporta 10.00 a menos do que o esperado (60.00).
        $result = app(SalePaymentService::class)->reconcileWebhookPayment($payment, 50.0);

        $this->assertSame(PaymentStatus::Divergent, $result->status);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'divergent']);
        // Venda NÃO é confirmado automaticamente numa divergência.
        $this->assertFalse((bool) Sale::where('uuid', $order['uuid'])->value('is_paid'));
    }

    #[Test]
    public function tenant_cannot_create_a_charge_for_another_tenants_order(): void
    {
        $this->grantPermission('sales', 'update');

        // Venda de OUTRO tenant, montado direto (o usuário autenticado é do
        // tenant do setUp).
        $otherTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $client = $this->createClient($otherTenant->id);

        $foreignOrder = Sale::create([
            'tenant_id' => $otherTenant->id,
            'final_customer_id' => $client->id,
            'is_installment' => false,
            'total_amount' => 30,
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        $this->auth()->postJson("/api/v1/sales/{$foreignOrder->uuid}/payment-charge")
            ->assertStatus(404);

        $this->assertSame(0, Payment::where('payable_type', Sale::class)->where('payable_id', $foreignOrder->id)->count());
    }
}
