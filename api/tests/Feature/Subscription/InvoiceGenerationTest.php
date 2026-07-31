<?php

namespace Tests\Feature\Subscription;

use App\Services\Subscription\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    #[Test]
    public function monthly_invoice_has_no_discount(): void
    {
        $plan = $this->createPlan();
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);
        $subscription = $this->createSubscription(['plan' => $plan, 'plan_price' => $price, 'billing_period' => 'monthly']);

        $invoice = $this->service()->generateForCycle($subscription);

        $this->assertEquals(99.90, (float) $invoice->amount_gross);
        $this->assertEquals(0.0, (float) $invoice->discount_amount);
        $this->assertEquals(99.90, (float) $invoice->amount_net);
        $this->assertSame('open', $invoice->status);
    }

    #[Test]
    public function quarterly_invoice_applies_ten_percent_discount(): void
    {
        $plan = $this->createPlan();
        // 99.90 * 3 = 299.70, 10% = 29.97, net = 269.73
        $price = $this->createPlanPrice($plan, 'quarterly', 299.70, 10);
        $subscription = $this->createSubscription(['plan' => $plan, 'plan_price' => $price, 'billing_period' => 'quarterly']);

        $invoice = $this->service()->generateForCycle($subscription);

        $this->assertEquals(299.70, (float) $invoice->amount_gross);
        $this->assertEquals(29.97, (float) $invoice->discount_amount);
        $this->assertEquals(269.73, (float) $invoice->amount_net);
    }

    #[Test]
    public function yearly_invoice_applies_twenty_percent_discount(): void
    {
        $plan = $this->createPlan();
        // 99.90 * 12 = 1198.80, 20% = 239.76, net = 959.04
        $price = $this->createPlanPrice($plan, 'yearly', 1198.80, 20);
        $subscription = $this->createSubscription(['plan' => $plan, 'plan_price' => $price, 'billing_period' => 'yearly']);

        $invoice = $this->service()->generateForCycle($subscription);

        $this->assertEquals(1198.80, (float) $invoice->amount_gross);
        $this->assertEquals(239.76, (float) $invoice->discount_amount);
        $this->assertEquals(959.04, (float) $invoice->amount_net);
    }
}
