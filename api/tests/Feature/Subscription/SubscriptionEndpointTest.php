<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Permissions\Concerns\SetsUpTenantScopedUser;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

class SubscriptionEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpTenantScopedUser;
    use CreatesSubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.environment', 'test');
        Config::set('services.mercadopago.test_payer_email', null);
        Config::set('services.mercadopago.webhook_secret', 'fake-secret');

        $this->setUpTenantScopedUser('sub-owner@example.com', 'owner', 'Proprietário');
        $this->grantPermission('subscription', 'read');
        $this->grantPermission('subscription', 'update');
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function allowPlanSubscription(int $planId): void
    {
        $functionalityId = DB::table('functionalities')->where('slug', 'subscription')->value('id');

        if ($functionalityId === null) {
            $functionalityId = DB::table('functionalities')->insertGetId([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Assinatura',
                'slug' => 'subscription',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'plan_id' => $planId,
            'functionality_id' => $functionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function show_returns_null_when_tenant_has_no_subscription(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function show_returns_current_subscription_with_invoices(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id]);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription')
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $subscription->uuid)
            ->assertJsonPath('data.status', 'trialing');
    }

    #[Test]
    public function store_creates_and_authorizes_a_subscription_for_the_current_tenant_plan_without_redirecting(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_checkout_1',
                'status' => 'authorized',
            ], 201),
        ]);

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'card_token' => 'card_token_checkout_1',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'trialing');

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'preapproval_id' => 'preapproval_checkout_1',
        ]);

        // O payer_email do Preapproval é sempre o do proprietário real do
        // tenant. Neste teste, o próprio usuário autenticado já é o dono
        // da empresa e contrata o plano sem redirecionamento.
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/preapproval'
                && $request['payer_email'] === 'sub-owner@example.com'
                && $request['card_token_id'] === 'card_token_checkout_1'
                && $request['status'] === 'authorized';
        });
    }

    #[Test]
    public function store_rejects_a_paid_plan_without_a_card_token(): void
    {
        Http::fake();

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_CARD_TOKEN_REQUIRED');

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $this->tenant->id]);
        Http::assertNothingSent();
    }

    #[Test]
    public function store_fails_when_mercado_pago_does_not_authorize_the_card(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_pending_1',
                'status' => 'pending',
            ], 201),
        ]);

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'card_token' => 'card_token_rejected',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE');

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function store_uses_the_configured_test_payer_email_when_mercadopago_is_in_test_mode(): void
    {
        Config::set('services.mercadopago.environment', 'test');
        Config::set('services.mercadopago.test_payer_email', 'mreisf.contato@gmail.com');

        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_checkout_test_payer',
                'status' => 'authorized',
            ], 201),
        ]);

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'card_token' => 'card_token_test_payer',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'trialing');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/preapproval'
                && $request['payer_email'] === 'mreisf.contato@gmail.com';
        });
    }

    #[Test]
    public function store_creates_a_subscription_for_a_chosen_plan_and_syncs_the_tenants_default_plan(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_chosen_1',
                'status' => 'authorized',
            ], 201),
        ]);

        $defaultPlan = $this->createPlan('pegaticket');
        $this->createPlanPrice($defaultPlan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($defaultPlan->id);

        $chosenPlan = $this->createPlan('plano-premium');
        $this->createPlanPrice($chosenPlan, 'monthly', 299.90, 0);
        $this->allowPlanSubscription($chosenPlan->id);

        $this->tenant->plan_id = $defaultPlan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'plan_id' => $chosenPlan->uuid,
                'card_token' => 'card_token_chosen_1',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'trialing');

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $chosenPlan->id,
            'billing_period' => 'monthly',
            'preapproval_id' => 'preapproval_chosen_1',
        ]);

        // tenants.plan_id passou a ser o plano ESCOLHIDO na primeira
        // contratação, não mais o padrão do cadastro — mesma consistência
        // já garantida por changePlan() (fonte das funcionalidades
        // liberadas).
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'plan_id' => $chosenPlan->id,
        ]);
    }

    #[Test]
    public function store_rejects_an_inactive_plan_id(): void
    {
        $defaultPlan = $this->createPlan('pegaticket');
        $this->createPlanPrice($defaultPlan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($defaultPlan->id);
        $this->tenant->plan_id = $defaultPlan->id;
        $this->tenant->save();

        $inactivePlan = $this->createPlan('inativo');
        $inactivePlan->is_active = false;
        $inactivePlan->save();
        $this->createPlanPrice($inactivePlan, 'monthly', 49.90, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'plan_id' => $inactivePlan->uuid,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('plan_id');

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function store_rejects_a_plan_id_that_does_not_exist(): void
    {
        $defaultPlan = $this->createPlan('pegaticket');
        $this->createPlanPrice($defaultPlan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($defaultPlan->id);
        $this->tenant->plan_id = $defaultPlan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'plan_id' => (string) \Illuminate\Support\Str::uuid(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    #[Test]
    public function store_rejects_when_the_tenant_already_has_a_subscription(): void
    {
        $plan = $this->createPlan('plano-plus');
        $price = $this->createPlanPrice($plan, 'monthly', 149.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'active',
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_ALREADY_EXISTS');
    }

    #[Test]
    public function store_allows_a_new_subscription_when_the_previous_one_was_canceled(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_rehire_1',
                'status' => 'authorized',
            ], 201),
        ]);

        $oldPlan = $this->createPlan('pegaticket');
        $oldPrice = $this->createPlanPrice($oldPlan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($oldPlan->id);

        $newPlan = $this->createPlan('plano-premium');
        $this->createPlanPrice($newPlan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($newPlan->id);

        $this->tenant->plan_id = $oldPlan->id;
        $this->tenant->save();

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $oldPlan,
            'plan_price' => $oldPrice,
            'status' => 'canceled',
            'canceled_at' => now()->subDay(),
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'plan_id' => $newPlan->uuid,
                'card_token' => 'card_token_rehire_1',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'trialing');

        $this->assertDatabaseCount('subscriptions', 2);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $newPlan->id,
            'preapproval_id' => 'preapproval_rehire_1',
            'status' => 'trialing',
        ]);
    }

    #[Test]
    public function tenant_cannot_see_another_tenants_subscription(): void
    {
        // Assinatura de OUTRO tenant.
        $otherTenant = $this->createTenant();
        $otherSub = $this->createSubscription(['tenant_id' => $otherTenant->id]);

        // O tenant ativo (A) não tem assinatura — deve receber null, nunca a de B.
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription')
            ->assertStatus(200);

        $this->assertNull($response->json('data'));
    }

    #[Test]
    public function cancel_immediately_marks_canceled(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/cancel', ['immediately' => true])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $fresh = $subscription->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertNotNull($fresh->canceled_at);
        $this->assertFalse((bool) $fresh->auto_renew);
    }

    #[Test]
    public function cancel_at_cycle_end_schedules_cancellation(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/cancel', ['immediately' => false])
            ->assertStatus(200);

        $fresh = $subscription->fresh();
        $this->assertSame('cancel_scheduled', $fresh->status);
        $this->assertNotNull($fresh->cancel_at);
    }

    #[Test]
    public function tenant_cannot_cancel_another_tenants_subscription(): void
    {
        $otherTenant = $this->createTenant();
        $otherSub = $this->createSubscription(['tenant_id' => $otherTenant->id, 'status' => 'active']);

        // O tenant ativo não tem assinatura própria → 404, e a de B fica intacta.
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/cancel', ['immediately' => true])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');

        $this->assertSame('active', $otherSub->fresh()->status);
    }

    #[Test]
    public function withdrawal_is_allowed_within_seven_days(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'trialing']);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/withdrawal')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.protocol'));
        $this->assertSame('canceled', $subscription->fresh()->status);
        $this->assertDatabaseHas('refunds', ['protocol' => $response->json('data.protocol'), 'status' => 'requested']);
    }

    #[Test]
    public function withdrawal_requests_a_real_refund_when_the_subscription_has_a_paid_mercadopago_invoice(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders/ORD_SUBSCRIPTION_1/refund' => Http::response([
                'id' => 'refund_subscription_1',
            ], 201),
        ]);

        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $invoice = \App\Models\Subscription\Invoice::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->toDateString(),
            'amount_gross' => '99.90',
            'discount_amount' => '0.00',
            'amount_net' => '99.90',
            'status' => 'paid',
        ]);

        \App\Models\Subscription\Payment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->id,
            'provider' => 'mercadopago',
            'provider_charge_id' => 'ORD_SUBSCRIPTION_1',
            'method' => 'card',
            'amount' => '99.90',
            'status' => 'paid',
            'paid_at' => now(),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'metadata' => ['transaction_id' => 'txn_subscription_1'],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/withdrawal')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('refunds', [
            'protocol' => $response->json('data.protocol'),
            'provider_refund_id' => 'refund_subscription_1',
            'status' => 'requested',
            'amount' => '99.90',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/v1/orders/ORD_SUBSCRIPTION_1/refund'
                && $request->hasHeader('Authorization', 'Bearer TEST-fake-token')
                && $request->hasHeader('X-Idempotency-Key');
        });
    }

    /**
     * Achado de auditoria: findCurrentForTenant() não filtra por status —
     * um duplo-clique/replay no endpoint de arrependimento encontrava a
     * MESMA assinatura já cancelada e criava um SEGUNDO Refund local (e
     * disparava um segunda venda de estorno real no PSP a cada chamada).
     */
    #[Test]
    public function withdrawal_cannot_be_requested_twice_for_the_same_subscription(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'trialing']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/withdrawal')
            ->assertStatus(200);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/withdrawal')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_SUBSCRIPTION_TRANSITION');

        $this->assertSame(1, \App\Models\Subscription\Refund::count());
    }

    #[Test]
    public function withdrawal_is_denied_after_seven_days(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $subscription->created_at = now()->subDays(8);
        $subscription->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/withdrawal')
            ->assertStatus(422)
            ->assertJsonPath('code', 'WITHDRAWAL_WINDOW_EXPIRED');

        $this->assertSame('active', $subscription->fresh()->status);
    }

    #[Test]
    public function renew_reverts_a_scheduled_cancellation(): void
    {
        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'status' => 'cancel_scheduled',
            'cancel_at' => now()->addDays(5),
            'auto_renew' => false,
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/renew')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $fresh = $subscription->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNull($fresh->cancel_at);
        $this->assertTrue((bool) $fresh->auto_renew);
    }

    #[Test]
    public function renew_is_rejected_when_there_is_no_scheduled_cancellation(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/renew')
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_SUBSCRIPTION_TRANSITION');
    }

    #[Test]
    public function tenant_cannot_renew_another_tenants_subscription(): void
    {
        $otherTenant = $this->createTenant();
        $otherSub = $this->createSubscription([
            'tenant_id' => $otherTenant->id,
            'status' => 'cancel_scheduled',
            'cancel_at' => now()->addDays(5),
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/renew')
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');

        $this->assertSame('cancel_scheduled', $otherSub->fresh()->status);
    }

    #[Test]
    public function updates_the_payment_method_of_an_active_preapproval(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_card_1' => Http::response([
                'id' => 'preapproval_card_1',
                'status' => 'authorized',
            ], 200),
        ]);

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'preapproval_id' => 'preapproval_card_1',
        ]);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/subscription/payment-method', ['card_token' => 'card_token_abc'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscription_events', [
            'type' => 'payment_method_updated',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/preapproval/preapproval_card_1'
                && $request->method() === 'PUT'
                && $request['card_token_id'] === 'card_token_abc';
        });
    }

    #[Test]
    public function payment_method_update_is_rejected_when_there_is_no_active_preapproval(): void
    {
        $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'trialing']);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/subscription/payment-method', ['card_token' => 'card_token_abc'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_NO_ACTIVE_PREAPPROVAL');
    }

    #[Test]
    public function tenant_cannot_update_payment_method_of_another_tenants_subscription(): void
    {
        $otherTenant = $this->createTenant();
        $this->createSubscription([
            'tenant_id' => $otherTenant->id,
            'status' => 'active',
            'preapproval_id' => 'preapproval_other',
        ]);

        $this->withHeaders($this->auth())
            ->putJson('/api/v1/subscription/payment-method', ['card_token' => 'card_token_abc'])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');
    }

    #[Test]
    public function lists_the_tenants_invoice_history_paginated(): void
    {
        $subscription = $this->createSubscription(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        for ($i = 0; $i < 3; $i++) {
            \App\Models\Subscription\Invoice::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'subscription_id' => $subscription->id,
                'tenant_id' => $this->tenant->id,
                'competence_period' => now()->subMonths($i)->format('Y-m'),
                'due_date' => now()->subMonths($i)->toDateString(),
                'amount_gross' => '99.90',
                'discount_amount' => '0.00',
                'amount_net' => '99.90',
                'status' => $i === 0 ? 'open' : 'paid',
            ]);
        }

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/invoices?per_page=2')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.pagination.total'));
    }

    #[Test]
    public function tenant_cannot_see_another_tenants_invoices(): void
    {
        $otherTenant = $this->createTenant();
        $otherSub = $this->createSubscription(['tenant_id' => $otherTenant->id, 'status' => 'active']);

        \App\Models\Subscription\Invoice::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => $otherSub->id,
            'tenant_id' => $otherTenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->toDateString(),
            'amount_gross' => '99.90',
            'discount_amount' => '0.00',
            'amount_net' => '99.90',
            'status' => 'paid',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/invoices')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    #[Test]
    public function creates_a_pix_charge_for_an_open_subscription_invoice(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD_SUB_PIX_1',
                'total_amount' => '99.90',
                'transactions' => [
                    'payments' => [[
                        'status' => 'pending',
                        'payment_method' => [
                            'qr_code' => '000201pixpegaticket',
                            'ticket_url' => 'https://www.mercadopago.com.br/payments/pix/1',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $plan = $this->createPlan('pegaticket');
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($plan->id);
        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();
        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'active',
        ]);

        $invoice = \App\Models\Subscription\Invoice::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_gross' => '99.90',
            'discount_amount' => '0.00',
            'amount_net' => '99.90',
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/subscription/invoices/{$invoice->uuid}/pix-charge")
            ->assertStatus(201)
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.metadata.qr_code', '000201pixpegaticket');

        $paymentUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('payments', [
            'uuid' => $paymentUuid,
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->id,
            'method' => 'pix',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function reuses_the_same_pending_pix_charge_for_the_same_invoice(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD_SUB_PIX_2',
                'total_amount' => '99.90',
                'transactions' => [
                    'payments' => [[
                        'status' => 'pending',
                        'payment_method' => [
                            'qr_code' => '000201pixpegaticket-repeat',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $plan = $this->createPlan('pegaticket');
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($plan->id);
        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();
        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'active',
        ]);

        $invoice = \App\Models\Subscription\Invoice::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_gross' => '99.90',
            'discount_amount' => '0.00',
            'amount_net' => '99.90',
            'status' => 'open',
        ]);

        $first = $this->withHeaders($this->auth())
            ->postJson("/api/v1/subscription/invoices/{$invoice->uuid}/pix-charge")
            ->assertStatus(201);

        $second = $this->withHeaders($this->auth())
            ->postJson("/api/v1/subscription/invoices/{$invoice->uuid}/pix-charge")
            ->assertStatus(201);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        Http::assertSentCount(1);
    }

    #[Test]
    public function rejects_pix_charge_for_a_paid_subscription_invoice(): void
    {
        $plan = $this->createPlan('pegaticket');
        $price = $this->createPlanPrice($plan, 'monthly', 99.90, 0);
        $this->allowPlanSubscription($plan->id);
        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'status' => 'active',
        ]);

        $invoice = \App\Models\Subscription\Invoice::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'competence_period' => now()->format('Y-m'),
            'due_date' => now()->subDay()->toDateString(),
            'amount_gross' => '99.90',
            'discount_amount' => '0.00',
            'amount_net' => '99.90',
            'status' => 'paid',
        ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/subscription/invoices/{$invoice->uuid}/pix-charge")
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_INVOICE_PAYMENT_STATE');
    }

    #[Test]
    public function plan_pricing_returns_error_when_tenant_has_no_plan(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/plan-pricing')
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_PLAN_REQUIRED');
    }

    #[Test]
    public function plan_pricing_returns_real_prices_and_functionalities_for_the_tenants_current_plan(): void
    {
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 100.00, 0);
        $this->createPlanPrice($plan, 'quarterly', 300.00, 10);
        $this->createPlanPrice($plan, 'yearly', 1200.00, 20);

        $functionalityId = DB::table('functionalities')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Vendas',
            'slug' => 'sales-' . \Illuminate\Support\Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_functionalities')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'plan_id' => $plan->id,
            'functionality_id' => $functionalityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant->fill(['plan_id' => $plan->id]);
        $this->tenant->save();
        $this->allowPlanSubscription($plan->id);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/plan-pricing')
            ->assertStatus(200)
            ->assertJsonPath('data.plan.uuid', $plan->uuid)
            ->assertJsonCount(3, 'data.billing_periods')
            ->assertJsonCount(2, 'data.functionalities');

        $periods = collect($response->json('data.billing_periods'))->keyBy('billing_period');

        $this->assertSame('100.00', $periods['monthly']['total_amount']);
        $this->assertEquals(0, $periods['monthly']['discount_percent']);

        $this->assertSame('300.00', $periods['quarterly']['list_amount']);
        $this->assertSame('270.00', $periods['quarterly']['total_amount']);
        $this->assertEquals(10, $periods['quarterly']['discount_percent']);

        $this->assertSame('1200.00', $periods['yearly']['list_amount']);
        $this->assertSame('960.00', $periods['yearly']['total_amount']);
        $this->assertSame('80.00', $periods['yearly']['monthly_equivalent']);
    }

    #[Test]
    public function tenant_cannot_see_another_tenants_plan_pricing(): void
    {
        $ownPlan = $this->createPlan();
        $this->createPlanPrice($ownPlan, 'monthly', 50.00, 0);
        $this->tenant->fill(['plan_id' => $ownPlan->id]);
        $this->tenant->save();
        $this->allowPlanSubscription($ownPlan->id);

        $otherPlan = $this->createPlan();
        $this->createPlanPrice($otherPlan, 'monthly', 999.00, 0);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/plan-pricing')
            ->assertStatus(200);

        $this->assertSame($ownPlan->uuid, $response->json('data.plan.uuid'));
        $this->assertNotSame($otherPlan->uuid, $response->json('data.plan.uuid'));
    }

    #[Test]
    public function plan_pricing_returns_prices_for_a_different_plan_when_plan_id_is_given(): void
    {
        $defaultPlan = $this->createPlan('pegaticket');
        $this->createPlanPrice($defaultPlan, 'monthly', 99.90, 0);
        $this->tenant->fill(['plan_id' => $defaultPlan->id]);
        $this->tenant->save();
        $this->allowPlanSubscription($defaultPlan->id);

        $otherPlan = $this->createPlan('plano-premium');
        $this->createPlanPrice($otherPlan, 'monthly', 299.90, 0);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/plan-pricing?plan_id=' . $otherPlan->uuid)
            ->assertStatus(200);

        $this->assertSame($otherPlan->uuid, $response->json('data.plan.uuid'));
        $this->assertNotSame($defaultPlan->uuid, $response->json('data.plan.uuid'));
    }

    #[Test]
    public function plan_pricing_returns_not_found_for_an_inactive_plan_id(): void
    {
        $defaultPlan = $this->createPlan('pegaticket');
        $this->createPlanPrice($defaultPlan, 'monthly', 99.90, 0);
        $this->tenant->fill(['plan_id' => $defaultPlan->id]);
        $this->tenant->save();
        $this->allowPlanSubscription($defaultPlan->id);

        $inactivePlan = $this->createPlan('inativo');
        $inactivePlan->is_active = false;
        $inactivePlan->save();
        $this->createPlanPrice($inactivePlan, 'monthly', 49.90, 0);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/plan-pricing?plan_id=' . $inactivePlan->uuid)
            ->assertStatus(404)
            ->assertJsonPath('code', 'PLAN_NOT_FOUND');
    }

    #[Test]
    public function available_plans_returns_error_when_tenant_has_no_plan(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/available-plans')
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_PLAN_REQUIRED');
    }

    #[Test]
    public function available_plans_lists_active_plans_except_the_current_one_with_real_pricing(): void
    {
        $currentPlan = $this->createPlan('atual');
        $currentPrice = $this->createPlanPrice($currentPlan, 'monthly', 100.00, 0);
        $this->allowPlanSubscription($currentPlan->id);
        $this->tenant->plan_id = $currentPlan->id;
        $this->tenant->save();

        // Tenant já tem uma assinatura de verdade nesse plano — é aqui que
        // faz sentido excluir o plano atual da lista (é uma troca).
        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $currentPlan,
            'plan_price' => $currentPrice,
            'status' => 'active',
        ]);

        $otherPlan = $this->createPlan('outro');
        $this->createPlanPrice($otherPlan, 'monthly', 200.00, 0);

        $inactivePlan = $this->createPlan('inativo');
        $inactivePlan->is_active = false;
        $inactivePlan->save();
        $this->createPlanPrice($inactivePlan, 'monthly', 50.00, 0);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/available-plans')
            ->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('plan.uuid');

        $this->assertTrue($uuids->contains($otherPlan->uuid));
        $this->assertFalse($uuids->contains($currentPlan->uuid));
        $this->assertFalse($uuids->contains($inactivePlan->uuid));
    }

    #[Test]
    public function available_plans_does_not_exclude_the_default_plan_before_the_first_subscription_exists(): void
    {
        // Sem assinatura ainda: tenants.plan_id é só o padrão do cadastro,
        // não um plano "contratado" de fato — a lista de opções da primeira
        // contratação precisa incluí-lo também.
        $defaultPlan = $this->createPlan('padrao');
        $this->createPlanPrice($defaultPlan, 'monthly', 100.00, 0);
        $this->allowPlanSubscription($defaultPlan->id);
        $this->tenant->plan_id = $defaultPlan->id;
        $this->tenant->save();

        $otherPlan = $this->createPlan('outro');
        $this->createPlanPrice($otherPlan, 'monthly', 200.00, 0);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/available-plans')
            ->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('plan.uuid');

        $this->assertTrue($uuids->contains($otherPlan->uuid));
        $this->assertTrue($uuids->contains($defaultPlan->uuid));
    }

    #[Test]
    public function available_plans_does_not_exclude_the_current_plan_when_the_latest_subscription_is_canceled(): void
    {
        $currentPlan = $this->createPlan('padrao');
        $currentPrice = $this->createPlanPrice($currentPlan, 'monthly', 100.00, 0);
        $this->allowPlanSubscription($currentPlan->id);
        $this->tenant->plan_id = $currentPlan->id;
        $this->tenant->save();

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $currentPlan,
            'plan_price' => $currentPrice,
            'status' => 'canceled',
            'canceled_at' => now()->subDay(),
        ]);

        $otherPlan = $this->createPlan('outro');
        $this->createPlanPrice($otherPlan, 'monthly', 200.00, 0);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/available-plans')
            ->assertStatus(200);

        $uuids = collect($response->json('data'))->pluck('plan.uuid');

        $this->assertTrue($uuids->contains($otherPlan->uuid));
        $this->assertTrue($uuids->contains($currentPlan->uuid));
    }

    #[Test]
    public function change_plan_upgrades_from_a_free_plan_to_a_paid_plan_and_creates_a_preapproval(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_upgrade_1',
                'status' => 'authorized',
            ], 201),
        ]);

        $freePlan = $this->createPlan('gratis');
        $freePrice = $this->createPlanPrice($freePlan, 'monthly', 0, 0);
        $this->allowPlanSubscription($freePlan->id);

        $this->tenant->plan_id = $freePlan->id;
        $this->tenant->save();

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $freePlan,
            'plan_price' => $freePrice,
            'billing_period' => 'monthly',
            'status' => 'active',
        ]);

        $paidPlan = $this->createPlan('paga');
        $this->createPlanPrice($paidPlan, 'monthly', 199.90, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $paidPlan->uuid,
                'billing_period' => 'monthly',
                'card_token' => 'card_token_upgrade_1',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.plan.uuid', $paidPlan->uuid);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $paidPlan->id,
            'preapproval_id' => 'preapproval_upgrade_1',
        ]);

        $this->assertSame($paidPlan->id, $this->tenant->fresh()->plan_id);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mercadopago.com/preapproval'
            && $request['payer_email'] === 'sub-owner@example.com'
            && $request['card_token_id'] === 'card_token_upgrade_1'
            && $request['status'] === 'authorized');
    }

    #[Test]
    public function change_plan_to_a_paid_plan_without_a_card_token_is_rejected(): void
    {
        Http::fake();

        $freePlan = $this->createPlan('gratis-ct');
        $freePrice = $this->createPlanPrice($freePlan, 'monthly', 0, 0);
        $this->allowPlanSubscription($freePlan->id);

        $this->tenant->plan_id = $freePlan->id;
        $this->tenant->save();

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $freePlan,
            'plan_price' => $freePrice,
            'billing_period' => 'monthly',
            'status' => 'active',
        ]);

        $paidPlan = $this->createPlan('paga-ct');
        $this->createPlanPrice($paidPlan, 'monthly', 199.90, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $paidPlan->uuid,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SUBSCRIPTION_CARD_TOKEN_REQUIRED');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $freePlan->id,
        ]);
        Http::assertNothingSent();
    }

    #[Test]
    public function change_plan_downgrades_from_a_paid_plan_to_a_free_plan_and_cancels_the_old_preapproval(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval/preapproval_old_1' => Http::response([
                'id' => 'preapproval_old_1',
                'status' => 'cancelled',
            ], 200),
        ]);

        $paidPlan = $this->createPlan('paga2');
        $paidPrice = $this->createPlanPrice($paidPlan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($paidPlan->id);

        $this->tenant->plan_id = $paidPlan->id;
        $this->tenant->save();

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $paidPlan,
            'plan_price' => $paidPrice,
            'billing_period' => 'monthly',
            'status' => 'active',
            'preapproval_id' => 'preapproval_old_1',
        ]);

        $freePlan = $this->createPlan('gratis2');
        $this->createPlanPrice($freePlan, 'monthly', 0, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $freePlan->uuid,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.plan.uuid', $freePlan->uuid);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $freePlan->id,
            'preapproval_id' => null,
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mercadopago.com/preapproval/preapproval_old_1'
            && $request->method() === 'PUT'
            && $request['status'] === 'cancelled');
    }

    #[Test]
    public function change_plan_between_two_paid_plans_creates_a_new_preapproval_and_cancels_the_old_one(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_new_2',
                'status' => 'authorized',
            ], 201),
            'api.mercadopago.com/preapproval/preapproval_old_2' => Http::response([
                'id' => 'preapproval_old_2',
                'status' => 'cancelled',
            ], 200),
        ]);

        $oldPlan = $this->createPlan('plano-orbita');
        $oldPrice = $this->createPlanPrice($oldPlan, 'monthly', 149.90, 0);
        $this->allowPlanSubscription($oldPlan->id);

        $this->tenant->plan_id = $oldPlan->id;
        $this->tenant->save();

        $subscription = $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $oldPlan,
            'plan_price' => $oldPrice,
            'billing_period' => 'monthly',
            'status' => 'active',
            'preapproval_id' => 'preapproval_old_2',
        ]);

        $newPlan = $this->createPlan('plano-constelacao');
        $this->createPlanPrice($newPlan, 'monthly', 299.90, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $newPlan->uuid,
                'billing_period' => 'monthly',
                'card_token' => 'card_token_new_2',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $newPlan->id,
            'preapproval_id' => 'preapproval_new_2',
        ]);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/preapproval' && $r->method() === 'POST');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/preapproval/preapproval_old_2' && $r->method() === 'PUT');
    }

    #[Test]
    public function change_plan_rejects_when_plan_and_period_are_unchanged(): void
    {
        $plan = $this->createPlan();
        $price = $this->createPlanPrice($plan, 'monthly', 100.00, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'billing_period' => 'monthly',
            'status' => 'active',
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $plan->uuid,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_SUBSCRIPTION_TRANSITION');
    }

    #[Test]
    public function change_plan_is_rejected_when_subscription_has_a_scheduled_cancellation(): void
    {
        $plan = $this->createPlan();
        $price = $this->createPlanPrice($plan, 'monthly', 100.00, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->createSubscription([
            'tenant_id' => $this->tenant->id,
            'plan' => $plan,
            'plan_price' => $price,
            'billing_period' => 'monthly',
            'status' => 'cancel_scheduled',
            'cancel_at' => now()->addDays(3),
        ]);

        $otherPlan = $this->createPlan();
        $this->createPlanPrice($otherPlan, 'monthly', 200.00, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $otherPlan->uuid,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_SUBSCRIPTION_TRANSITION');
    }

    #[Test]
    public function tenant_cannot_change_plan_of_another_tenants_subscription(): void
    {
        $otherTenant = $this->createTenant();
        $otherPlan = $this->createPlan();
        $otherPrice = $this->createPlanPrice($otherPlan, 'monthly', 100.00, 0);
        $otherSub = $this->createSubscription([
            'tenant_id' => $otherTenant->id,
            'plan' => $otherPlan,
            'plan_price' => $otherPrice,
            'status' => 'active',
        ]);

        $newPlan = $this->createPlan();
        $this->createPlanPrice($newPlan, 'monthly', 200.00, 0);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription/change-plan', [
                'plan_id' => $newPlan->uuid,
                'billing_period' => 'monthly',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');

        $this->assertSame('active', $otherSub->fresh()->status);
    }

    #[Test]
    public function lists_the_tenants_subscription_history_paginated(): void
    {
        $plan = $this->createPlan();
        $price = $this->createPlanPrice($plan, 'monthly', 100.00, 0);

        for ($i = 0; $i < 3; $i++) {
            $this->createSubscription([
                'tenant_id' => $this->tenant->id,
                'plan' => $plan,
                'plan_price' => $price,
                'status' => $i === 0 ? 'active' : 'canceled',
            ]);
        }

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/history?per_page=2')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.pagination.total'));
    }

    #[Test]
    public function tenant_cannot_see_another_tenants_subscription_history(): void
    {
        $otherTenant = $this->createTenant();
        $this->createSubscription(['tenant_id' => $otherTenant->id]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/subscription/history')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    #[Test]
    public function store_returns_a_friendly_422_when_the_payment_provider_rejects_the_preapproval_call(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'message' => 'invalid auto_recurring.start_date',
            ], 400),
        ]);

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'card_token' => 'card_token_invalid_start_date',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE');

        $message = (string) $response->json('message');

        // A mensagem exibida ao proprietário nunca cita detalhe técnico
        // (Mercado Pago, backend, requisição, endpoint) — só orientação
        // simples do que fazer.
        $this->assertStringNotContainsStringIgnoringCase('mercado pago', $message);
        $this->assertStringNotContainsStringIgnoringCase('backend', $message);
        $this->assertStringNotContainsStringIgnoringCase('requisi', $message);

        // Nenhuma assinatura órfã fica gravada: a falha na criação do
        // Preapproval acontece DENTRO da transação de create(), então o
        // rollback também desfaz o registro local.
        $this->assertDatabaseMissing('subscriptions', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function store_returns_a_specific_message_when_mercadopago_rejects_mixed_real_and_test_accounts(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'message' => 'Both payer and collector must be real or test users',
                'status' => 400,
            ], 400),
        ]);

        $plan = $this->createPlan('plano-premium');
        $this->createPlanPrice($plan, 'monthly', 199.90, 0);
        $this->allowPlanSubscription($plan->id);

        $this->tenant->plan_id = $plan->id;
        $this->tenant->save();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/subscription', [
                'billing_period' => 'monthly',
                'accepted_terms' => true,
                'card_token' => 'card_token_mixed_accounts',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PAYMENT_PROVIDER_UNAVAILABLE')
            ->assertJsonPath(
                'message',
                __('messages.payment.payer_collector_mismatch')
            );

        $this->assertDatabaseMissing('subscriptions', [
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
