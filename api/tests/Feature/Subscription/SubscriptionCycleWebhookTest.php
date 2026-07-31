<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription\Subscription;
use App\Models\Subscription\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

/**
 * Cobrança recorrente real de assinatura via Mercado Pago Preapproval
 * (roadmap Fase B, item 1): webhook `subscription_authorized_payment`
 * confirma/rejeita o ciclo, período de graça de 7 dias e suspensão via
 * `subscriptions:enforce-grace-period`.
 */
class SubscriptionCycleWebhookTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    private const SECRET = 'whsec_cycle_test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.webhook_secret', self::SECRET);
    }

    private function signedHeaders(string $dataId, string $requestId, ?int $ts = null): array
    {
        $ts ??= time();
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, self::SECRET);

        return [
            'x-request-id' => $requestId,
            'x-signature' => "ts={$ts},v1={$v1}",
        ];
    }

    private function postAuthorizedPaymentEvent(string $notificationId, string $authorizedPaymentId): \Illuminate\Testing\TestResponse
    {
        $headers = $this->signedHeaders($authorizedPaymentId, (string) Str::uuid());

        return $this->postJson('/api/v1/webhooks/payments/mercadopago', [
            'id' => $notificationId,
            'type' => 'subscription_authorized_payment',
            'data' => ['id' => $authorizedPaymentId],
        ], $headers);
    }

    private function subscriptionWithPreapproval(array $overrides = []): Subscription
    {
        $plan = $this->createPlan();
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        return $this->createSubscription(array_merge([
            'plan' => $plan,
            'plan_price' => $price,
            'billing_period' => 'monthly',
            'status' => 'active',
            'preapproval_id' => 'preapproval_xyz',
            'current_period_start' => now()->subDays(30),
            'current_period_end' => now(),
            'next_charge_at' => now(),
        ], $overrides));
    }

    #[Test]
    public function processed_authorized_payment_marks_the_cycle_invoice_paid_and_extends_the_period(): void
    {
        $subscription = $this->subscriptionWithPreapproval();
        $previousPeriodEnd = $subscription->current_period_end;

        Http::fake([
            'api.mercadopago.com/authorized_payments/ap_1' => Http::response([
                'id' => 'ap_1',
                'preapproval_id' => 'preapproval_xyz',
                'status' => 'processed',
                'transaction_amount' => 99.90,
            ], 200),
        ]);

        $this->postAuthorizedPaymentEvent('evt_cycle_1', 'ap_1')->assertStatus(200);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->current_period_start->equalTo($previousPeriodEnd));
        $this->assertTrue($subscription->current_period_end->greaterThan($previousPeriodEnd));

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function rejected_authorized_payment_starts_the_grace_period(): void
    {
        $subscription = $this->subscriptionWithPreapproval();

        Http::fake([
            'api.mercadopago.com/authorized_payments/ap_2' => Http::response([
                'id' => 'ap_2',
                'preapproval_id' => 'preapproval_xyz',
                'status' => 'rejected',
                'transaction_amount' => 99.90,
            ], 200),
        ]);

        $this->postAuthorizedPaymentEvent('evt_cycle_fail_1', 'ap_2')->assertStatus(200);

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->grace_period_ends_at->isSameDay(now()->addDays(7)));
    }

    #[Test]
    public function duplicate_rejected_event_does_not_reset_the_grace_period_clock(): void
    {
        $subscription = $this->subscriptionWithPreapproval();

        Http::fake([
            'api.mercadopago.com/authorized_payments/ap_3' => Http::response([
                'id' => 'ap_3',
                'preapproval_id' => 'preapproval_xyz',
                'status' => 'rejected',
                'transaction_amount' => 99.90,
            ], 200),
        ]);

        $this->postAuthorizedPaymentEvent('evt_cycle_fail_dup', 'ap_3')->assertStatus(200);
        $subscription->refresh();
        $firstDeadline = $subscription->grace_period_ends_at;

        $this->travel(1)->hours();

        // Mesmo notification id -> webhook_events barra reprocessamento
        // (idempotência de entrega). Mesmo que reprocessasse,
        // startGracePeriod() é idempotente (não reinicia a contagem).
        $this->postAuthorizedPaymentEvent('evt_cycle_fail_dup', 'ap_3')->assertStatus(200);
        $subscription->refresh();

        $this->assertTrue($subscription->grace_period_ends_at->equalTo($firstDeadline));
        $this->assertSame(1, WebhookEvent::where('external_id', 'evt_cycle_fail_dup')->count());
    }

    #[Test]
    public function enforcement_command_suspends_subscriptions_past_the_grace_period(): void
    {
        $expired = $this->subscriptionWithPreapproval([
            'status' => 'past_due',
            'preapproval_id' => 'preapproval_expired',
            'grace_period_ends_at' => now()->subDay(),
        ]);

        $stillInGrace = $this->subscriptionWithPreapproval([
            'status' => 'past_due',
            'preapproval_id' => 'preapproval_in_grace',
            'grace_period_ends_at' => now()->addDays(2),
        ]);

        Artisan::call('subscriptions:enforce-grace-period');

        $expired->refresh();
        $stillInGrace->refresh();

        $this->assertSame('suspended', $expired->status);
        $this->assertSame('past_due', $stillInGrace->status);
    }

    #[Test]
    public function enforcement_command_is_idempotent_for_already_suspended_subscriptions(): void
    {
        $subscription = $this->subscriptionWithPreapproval([
            'status' => 'suspended',
            'grace_period_ends_at' => now()->subDay(),
        ]);

        Artisan::call('subscriptions:enforce-grace-period');
        Artisan::call('subscriptions:enforce-grace-period');

        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
        // Já entrou suspenso (fixture) — a máquina de estados não permite
        // suspended->suspended, então o comando não gera novo evento.
        $this->assertSame(
            0,
            $subscription->events()->where('type', 'suspended')->count()
        );
    }

    #[Test]
    public function processed_authorized_payment_with_divergent_amount_does_not_confirm_the_cycle(): void
    {
        $subscription = $this->subscriptionWithPreapproval();
        $previousPeriodEnd = $subscription->current_period_end;

        Http::fake([
            'api.mercadopago.com/authorized_payments/ap_divergent' => Http::response([
                'id' => 'ap_divergent',
                'preapproval_id' => 'preapproval_xyz',
                'status' => 'processed',
                // Valor reportado pelo MP diverge do plan_price contratado
                // (99.90) — nunca confia no webhook sozinho.
                'transaction_amount' => 1.00,
            ], 200),
        ]);

        $this->postAuthorizedPaymentEvent('evt_cycle_divergent', 'ap_divergent')->assertStatus(200);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->current_period_end->equalTo($previousPeriodEnd));

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'status' => 'divergent',
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'type' => 'cycle_payment_divergent',
        ]);
    }

    #[Test]
    public function successful_payment_reverts_a_suspended_subscription(): void
    {
        $subscription = $this->subscriptionWithPreapproval([
            'status' => 'suspended',
            'grace_period_ends_at' => now()->subDay(),
        ]);

        Http::fake([
            'api.mercadopago.com/authorized_payments/ap_4' => Http::response([
                'id' => 'ap_4',
                'preapproval_id' => 'preapproval_xyz',
                'status' => 'processed',
                'transaction_amount' => 99.90,
            ], 200),
        ]);

        $this->postAuthorizedPaymentEvent('evt_reactivate_1', 'ap_4')->assertStatus(200);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->grace_period_ends_at);
    }
}
