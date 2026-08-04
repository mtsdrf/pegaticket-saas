<?php

namespace Tests\Feature\Risk;

use App\Models\FinalCustomer\FinalCustomer;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
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
}
