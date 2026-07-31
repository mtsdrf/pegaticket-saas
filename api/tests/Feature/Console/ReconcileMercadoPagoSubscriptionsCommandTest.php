<?php

namespace Tests\Feature\Console;

use App\Models\Subscription\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class ReconcileMercadoPagoSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
    }

    #[Test]
    public function command_cancels_locally_when_remote_preapproval_is_cancelled(): void
    {
        $plan = $this->createPlan('prata');
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);
        $tenant = $this->createTenant();

        $subscription = $this->createSubscription([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'active',
            'preapproval_id' => 'preapproval_cancelled_remote',
        ]);

        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_cancelled_remote' => Http::response([
                'id' => 'preapproval_cancelled_remote',
                'status' => 'cancelled',
            ], 200),
        ]);

        Artisan::call('subscriptions:reconcile-mercadopago', ['--subscription_uuid' => $subscription->uuid]);

        $subscription->refresh();

        $this->assertSame('canceled', $subscription->status);
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertNotNull($subscription->canceled_at);
    }

    #[Test]
    public function command_activates_a_pending_subscription_when_remote_preapproval_is_authorized(): void
    {
        $plan = $this->createPlan('ouro');
        $price = $this->createPlanPrice($plan, 'monthly', 149.90, 0);
        $tenant = $this->createTenant();

        $subscription = $this->createSubscription([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'pending',
            'preapproval_id' => 'preapproval_authorized_remote',
        ]);

        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_authorized_remote' => Http::response([
                'id' => 'preapproval_authorized_remote',
                'status' => 'authorized',
            ], 200),
        ]);

        Artisan::call('subscriptions:reconcile-mercadopago', ['--subscription_uuid' => $subscription->uuid]);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
    }

    #[Test]
    public function command_keeps_local_status_when_remote_preapproval_state_is_not_actionable(): void
    {
        $plan = $this->createPlan('diamante');
        $price = $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $tenant = $this->createTenant();

        $subscription = $this->createSubscription([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'trialing',
            'preapproval_id' => 'preapproval_pending_remote',
        ]);

        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_pending_remote' => Http::response([
                'id' => 'preapproval_pending_remote',
                'status' => 'pending',
            ], 200),
        ]);

        Artisan::call('subscriptions:reconcile-mercadopago', ['--subscription_uuid' => $subscription->uuid]);

        $subscription->refresh();

        $this->assertSame('trialing', $subscription->status);
    }
}
