<?php

namespace Tests\Feature\Risk;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleRefund;
use App\Models\Subscription\Payment;
use App\Models\Tenant\Tenant;
use App\Services\Risk\RiskEngineService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Sales\Concerns\CreatesSaleFixtures;
use Tests\TestCase;

/**
 * Motor de risco básico (roadmap Fase 7) — RiskEngineService::evaluateSalePaid()
 * marca risk_flagged/risk_reason (só um alerta, nunca bloqueia) quando o
 * mesmo cliente/e-mail fez mais de PURCHASE_COUNT_THRESHOLD compras pagas
 * pro mesmo evento dentro de WINDOW_HOURS.
 */
class RiskEngineTest extends TestCase
{
    use CreatesSaleFixtures;
    use RefreshDatabase;

    private function tenantAndTicketType(): array
    {
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant '.Str::random(6),
            'slug' => 'tenant-'.Str::random(8),
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        $ticketType = $this->createProduct($tenant->id, ['price' => 50]);

        return [$tenant, $ticketType];
    }

    private function createPaidSale(int $tenantId, int $finalCustomerId, int $ticketTypeId, ?CarbonInterface $paidAt = null): Sale
    {
        $sale = Sale::create([
            'tenant_id' => $tenantId,
            'final_customer_id' => $finalCustomerId,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => true,
            'paid_at' => $paidAt ?? now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'tenant_id' => $tenantId,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        return $sale;
    }

    private function createFreeCourtesySale(int $tenantId, int $finalCustomerId, int $ticketTypeId, ?CarbonInterface $paidAt = null): Sale
    {
        $sale = Sale::create([
            'tenant_id' => $tenantId,
            'final_customer_id' => $finalCustomerId,
            'is_installment' => false,
            'total_amount' => 0,
            'is_paid' => true,
            'paid_at' => $paidAt ?? now(),
            'status' => 'confirmed',
            'origin' => 'staff',
        ]);

        SaleItem::create([
            'tenant_id' => $tenantId,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => 0,
            'line_total' => 0,
        ]);

        return $sale;
    }

    private function createAttemptSale(int $tenantId, int $finalCustomerId, int $ticketTypeId): Sale
    {
        $sale = Sale::create([
            'tenant_id' => $tenantId,
            'final_customer_id' => $finalCustomerId,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => false,
            'status' => 'pending',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'tenant_id' => $tenantId,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        return $sale;
    }

    private function createPaidSaleWithIp(int $tenantId, int $finalCustomerId, int $ticketTypeId, string $ip, ?CarbonInterface $paidAt = null): Sale
    {
        $sale = Sale::create([
            'tenant_id' => $tenantId,
            'final_customer_id' => $finalCustomerId,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => true,
            'paid_at' => $paidAt ?? now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
            'purchaser_ip' => $ip,
        ]);

        SaleItem::create([
            'tenant_id' => $tenantId,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketTypeId,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        return $sale;
    }

    private function createSaleRefund(Sale $sale, ?CarbonInterface $createdAt = null): SaleRefund
    {
        $refund = SaleRefund::create([
            'tenant_id' => $sale->tenant_id,
            'sale_id' => $sale->id,
            'type' => SaleRefund::TYPE_TOTAL,
            'amount' => $sale->total_amount,
            'reason' => 'Cliente desistiu',
            'refunded_at' => $createdAt ?? now(),
            'status' => SaleRefund::STATUS_REGISTERED,
        ]);

        if ($createdAt) {
            $refund->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $refund;
    }

    private function createPaymentForSale(Sale $sale, string $status, ?array $card = null, ?CarbonInterface $createdAt = null): Payment
    {
        $payment = Payment::create([
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'provider' => 'pagbank',
            'provider_charge_id' => 'CHAR_'.Str::random(10),
            'method' => 'credit_card',
            'amount' => $sale->total_amount,
            'status' => $status,
            'metadata' => $card ? ['card' => $card] : null,
        ]);

        if ($createdAt) {
            $payment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $payment;
    }

    #[Test]
    public function flags_a_sale_when_the_same_customer_has_repeated_failed_payment_attempts_in_a_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'cardtester@test.com']);

        for ($i = 0; $i < 3; $i++) {
            $attempt = $this->createAttemptSale($tenant->id, $customer->id, $ticketType->id);
            $this->createPaymentForSale($attempt, 'failed');
        }

        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertTrue((bool) $sale->risk_flagged);
        $this->assertStringContainsString('tentativas de pagamento recusadas/falhas', (string) $sale->risk_reason);
    }

    #[Test]
    public function does_not_flag_failed_payment_attempts_outside_the_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'old-cardtester@test.com']);

        for ($i = 0; $i < 3; $i++) {
            $attempt = $this->createAttemptSale($tenant->id, $customer->id, $ticketType->id);
            $this->createPaymentForSale($attempt, 'failed', null, now()->subMinutes(60));
        }

        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }

    #[Test]
    public function flags_a_sale_when_the_same_customer_uses_multiple_different_cards_in_a_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'multi-card@test.com']);

        $cards = [
            ['first_digits' => '411111', 'last_digits' => '1111'],
            ['first_digits' => '555555', 'last_digits' => '4444'],
            ['first_digits' => '222222', 'last_digits' => '2222'],
        ];

        foreach ($cards as $card) {
            $attempt = $this->createAttemptSale($tenant->id, $customer->id, $ticketType->id);
            $this->createPaymentForSale($attempt, 'authorized', $card);
        }

        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertTrue((bool) $sale->risk_flagged);
        $this->assertStringContainsString('cartões diferentes', (string) $sale->risk_reason);
    }

    #[Test]
    public function does_not_flag_the_same_card_reused_by_the_same_customer(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'loyal-card@test.com']);
        $card = ['first_digits' => '411111', 'last_digits' => '1111'];

        for ($i = 0; $i < 3; $i++) {
            $attempt = $this->createAttemptSale($tenant->id, $customer->id, $ticketType->id);
            $this->createPaymentForSale($attempt, 'authorized', $card);
        }

        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }

    #[Test]
    public function flags_a_sale_when_the_same_card_is_used_by_multiple_different_customers_in_a_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $card = ['first_digits' => '411111', 'last_digits' => '1111'];

        $customerA = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'victim-a@test.com']);
        $customerB = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'victim-b@test.com']);
        $customerC = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'victim-c@test.com']);

        $attemptB = $this->createAttemptSale($tenant->id, $customerB->id, $ticketType->id);
        $this->createPaymentForSale($attemptB, 'authorized', $card);

        $attemptC = $this->createAttemptSale($tenant->id, $customerC->id, $ticketType->id);
        $this->createPaymentForSale($attemptC, 'failed', $card);

        $saleA = Sale::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customerA->id,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $saleA->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $this->createPaymentForSale($saleA, 'paid', $card);

        app(RiskEngineService::class)->evaluateSalePaid($saleA->uuid);

        $saleA->refresh();
        $this->assertTrue((bool) $saleA->risk_flagged);
        $this->assertStringContainsString('usado por', (string) $saleA->risk_reason);
    }

    #[Test]
    public function does_not_flag_a_card_used_by_a_single_customer_across_tenants(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();
        [$otherTenant, $otherTicketType] = $this->tenantAndTicketType();

        $card = ['first_digits' => '411111', 'last_digits' => '1111'];

        $customerElsewhere = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'other-tenant@test.com']);
        $attempt = $this->createAttemptSale($otherTenant->id, $customerElsewhere->id, $otherTicketType->id);
        $this->createPaymentForSale($attempt, 'paid', $card);

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'lone-card@test.com']);

        $sale = Sale::create([
            'tenant_id' => $tenant->id,
            'final_customer_id' => $customer->id,
            'is_installment' => false,
            'total_amount' => 50,
            'is_paid' => true,
            'paid_at' => now(),
            'status' => 'confirmed',
            'origin' => 'storefront',
        ]);

        SaleItem::create([
            'tenant_id' => $tenant->id,
            'sale_id' => $sale->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 50,
            'line_total' => 50,
        ]);

        $this->createPaymentForSale($sale, 'paid', $card);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }

    #[Test]
    public function does_not_flag_repeated_zero_amount_courtesy_sales_for_the_same_event(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'guest-list@test.com']);

        $sale1 = $this->createFreeCourtesySale($tenant->id, $customer->id, $ticketType->id);
        $sale2 = $this->createFreeCourtesySale($tenant->id, $customer->id, $ticketType->id);
        $sale3 = $this->createFreeCourtesySale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale3->uuid);

        $sale1->refresh();
        $sale2->refresh();
        $sale3->refresh();
        $this->assertFalse((bool) $sale1->risk_flagged);
        $this->assertFalse((bool) $sale2->risk_flagged);
        $this->assertFalse((bool) $sale3->risk_flagged);
        $this->assertNull($sale3->risk_reason);
    }

    #[Test]
    public function flags_a_sale_when_the_same_customer_buys_repeatedly_for_the_same_event_in_a_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'scalper@test.com']);

        $sale1 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale2 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale3 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale3->uuid);

        $sale3->refresh();
        $this->assertTrue((bool) $sale3->risk_flagged);
        $this->assertNotEmpty($sale3->risk_reason);

        // Não retroage: só a venda avaliada recebe o flag.
        $sale1->refresh();
        $this->assertFalse((bool) $sale1->risk_flagged);
    }

    #[Test]
    public function does_not_flag_a_normal_single_purchase(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'normal@test.com']);
        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
        $this->assertNull($sale->risk_reason);
    }

    #[Test]
    public function does_not_flag_purchases_outside_the_time_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'old-buyer@test.com']);

        $this->createPaidSale($tenant->id, $customer->id, $ticketType->id, now()->subHours(48));
        $this->createPaidSale($tenant->id, $customer->id, $ticketType->id, now()->subHours(30));
        $sale3 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id, now());

        app(RiskEngineService::class)->evaluateSalePaid($sale3->uuid);

        $sale3->refresh();
        $this->assertFalse((bool) $sale3->risk_flagged);
    }

    #[Test]
    public function flags_a_sale_when_many_different_customers_pay_from_the_same_ip_in_a_short_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();
        $ip = '203.0.113.9';

        $customerA = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'bot-a@test.com']);
        $customerB = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'bot-b@test.com']);
        $customerC = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'bot-c@test.com']);
        $customerD = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'bot-d@test.com']);

        $this->createPaidSaleWithIp($tenant->id, $customerA->id, $ticketType->id, $ip);
        $this->createPaidSaleWithIp($tenant->id, $customerB->id, $ticketType->id, $ip);
        $this->createPaidSaleWithIp($tenant->id, $customerC->id, $ticketType->id, $ip);
        $sale4 = $this->createPaidSaleWithIp($tenant->id, $customerD->id, $ticketType->id, $ip);

        app(RiskEngineService::class)->evaluateSalePaid($sale4->uuid);

        $sale4->refresh();
        $this->assertTrue((bool) $sale4->risk_flagged);
        $this->assertStringContainsString('mesmo IP', (string) $sale4->risk_reason);
    }

    #[Test]
    public function does_not_flag_ip_velocity_when_same_customer_buys_repeatedly_from_the_same_ip(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();
        $ip = '203.0.113.20';

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'regular@test.com']);

        // Só 2 compras (abaixo de PURCHASE_COUNT_THRESHOLD=3) pra não
        // acionar a heurística 1 (compras repetidas) antes da de IP —
        // o objetivo aqui é isolar especificamente a heurística de IP:
        // 1 único cliente no IP nunca bate IP_VELOCITY_THRESHOLD=4.
        $this->createPaidSaleWithIp($tenant->id, $customer->id, $ticketType->id, $ip);
        $sale2 = $this->createPaidSaleWithIp($tenant->id, $customer->id, $ticketType->id, $ip);

        app(RiskEngineService::class)->evaluateSalePaid($sale2->uuid);

        $sale2->refresh();
        $this->assertFalse((bool) $sale2->risk_flagged);
    }

    #[Test]
    public function does_not_flag_ip_velocity_across_different_tenants_sharing_the_same_ip(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();
        [$otherTenant, $otherTicketType] = $this->tenantAndTicketType();
        $ip = '203.0.113.30';

        for ($i = 0; $i < 4; $i++) {
            $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => "other-tenant-$i@test.com"]);
            $this->createPaidSaleWithIp($otherTenant->id, $customer->id, $otherTicketType->id, $ip);
        }

        $customerA = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'lone-a@test.com']);
        $customerB = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'lone-b@test.com']);

        $this->createPaidSaleWithIp($tenant->id, $customerA->id, $ticketType->id, $ip);
        $sale2 = $this->createPaidSaleWithIp($tenant->id, $customerB->id, $ticketType->id, $ip);

        app(RiskEngineService::class)->evaluateSalePaid($sale2->uuid);

        $sale2->refresh();
        $this->assertFalse((bool) $sale2->risk_flagged);
    }

    #[Test]
    public function does_not_flag_a_sale_without_purchaser_ip(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'staff-sale@test.com']);
        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        app(RiskEngineService::class)->evaluateSalePaid($sale->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }

    #[Test]
    public function flags_the_sale_when_the_same_customer_has_multiple_refunds_in_the_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'refund-abuser@test.com']);

        $sale1 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale2 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale3 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        $this->createSaleRefund($sale1);
        $this->createSaleRefund($sale2);
        $refund3 = $this->createSaleRefund($sale3);

        app(RiskEngineService::class)->evaluateRefund($refund3->uuid);

        $sale3->refresh();
        $this->assertTrue((bool) $sale3->risk_flagged);
        $this->assertStringContainsString('reembolsos', (string) $sale3->risk_reason);
    }

    #[Test]
    public function does_not_flag_refund_abuse_for_a_single_refund(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'one-time-refund@test.com']);
        $sale = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $refund = $this->createSaleRefund($sale);

        app(RiskEngineService::class)->evaluateRefund($refund->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }

    #[Test]
    public function does_not_flag_refund_abuse_outside_the_window(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();

        $customer = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'old-refunder@test.com']);

        $sale1 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale2 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);
        $sale3 = $this->createPaidSale($tenant->id, $customer->id, $ticketType->id);

        $this->createSaleRefund($sale1, now()->subDays(60));
        $this->createSaleRefund($sale2, now()->subDays(45));
        $refund3 = $this->createSaleRefund($sale3, now());

        app(RiskEngineService::class)->evaluateRefund($refund3->uuid);

        $sale3->refresh();
        $this->assertFalse((bool) $sale3->risk_flagged);
    }

    #[Test]
    public function does_not_flag_refund_abuse_across_different_tenants(): void
    {
        [$tenant, $ticketType] = $this->tenantAndTicketType();
        [$otherTenant, $otherTicketType] = $this->tenantAndTicketType();

        $customerElsewhere = FinalCustomer::create(['uuid' => (string) Str::uuid(), 'email' => 'elsewhere@test.com']);
        $otherSale1 = $this->createPaidSale($otherTenant->id, $customerElsewhere->id, $otherTicketType->id);
        $otherSale2 = $this->createPaidSale($otherTenant->id, $customerElsewhere->id, $otherTicketType->id);
        $this->createSaleRefund($otherSale1);
        $this->createSaleRefund($otherSale2);

        // Mesmo FinalCustomer (mesma linha) comprando em outro tenant —
        // só 1 reembolso NESTE tenant, os 2 reembolsos de lá não devem
        // contar aqui mesmo sendo o mesmo cliente (isolamento por tenant).
        $sale = $this->createPaidSale($tenant->id, $customerElsewhere->id, $ticketType->id);
        $refund = $this->createSaleRefund($sale);

        app(RiskEngineService::class)->evaluateRefund($refund->uuid);

        $sale->refresh();
        $this->assertFalse((bool) $sale->risk_flagged);
    }
}
