<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription\Subscription;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Subscription\Concerns\CreatesSubscriptionFixtures;
use Tests\TestCase;

/**
 * Criação do Preapproval (assinatura automática recorrente) no Mercado
 * Pago ao contratar um plano pago (roadmap Fase B, item 1 — assinatura).
 * Http mockado — nenhuma chamada de rede real.
 */
class MercadoPagoPreapprovalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.payments.provider', 'mercadopago');
        Config::set('services.mercadopago.access_token', 'TEST-fake-token');
        Config::set('services.mercadopago.webhook_secret', 'fake-secret');
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    #[Test]
    public function creating_a_subscription_for_a_paid_plan_creates_and_authorizes_a_preapproval(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_abc123',
                'status' => 'authorized',
            ], 201),
        ]);

        $tenant = $this->createTenant();
        // tenants.email é contato opcional do estabelecimento — mesmo
        // presente aqui, NÃO deve ser usado como payer_email (decisão de
        // negócio: quem paga é sempre o PROPRIETÁRIO do tenant, dado por
        // createTenantOwner() abaixo).
        $tenant->forceFill(['email' => 'contato@fixture-tenant.test'])->save();
        $this->createTenantOwner($tenant, 'owner@fixture-tenant.test');
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        $subscription = $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1', 'card_token_abc123');

        $this->assertSame('preapproval_abc123', $subscription->preapproval_id);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.mercadopago.com/preapproval'
                && $request->hasHeader('Authorization', 'Bearer TEST-fake-token')
                && $request['payer_email'] === 'owner@fixture-tenant.test'
                && $request['card_token_id'] === 'card_token_abc123'
                && $request['status'] === 'authorized'
                && $request['auto_recurring']['frequency'] === 1
                && $request['auto_recurring']['frequency_type'] === 'months';
        });

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'type' => 'preapproval_created',
        ]);
    }

    #[Test]
    public function fails_safely_instead_of_sending_a_null_payer_email_when_none_is_resolvable(): void
    {
        // Sem tenant.email e sem usuário autenticado (chamada direta ao
        // Service, fora de um request HTTP) — POST /preapproval exige
        // payer_email; nunca deve ser enviado nulo/inventado.
        Http::fake();

        $tenant = $this->createTenant();
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mercadopago.missing_payer_email');

        $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1', 'card_token_abc123');
    }

    #[Test]
    public function creating_a_subscription_for_a_paid_plan_without_a_card_token_fails_before_calling_the_psp(): void
    {
        Http::fake();

        $tenant = $this->createTenant();
        $this->createTenantOwner($tenant, 'owner@fixture-tenant.test');
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        $this->expectException(\App\Exceptions\Subscription\CardTokenRequiredException::class);

        $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1');

        Http::assertNothingSent();
    }

    #[Test]
    public function creating_a_subscription_fails_when_mercado_pago_does_not_authorize_the_card(): void
    {
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'preapproval_rejected_1',
                'status' => 'pending',
            ], 201),
        ]);

        $tenant = $this->createTenant();
        $this->createTenantOwner($tenant, 'owner@fixture-tenant.test');
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        $this->expectException(\App\Exceptions\Payment\PaymentProviderException::class);
        $this->expectExceptionMessage('mercadopago.card_authorization_failed');

        $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1', 'card_token_rejected');

        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $tenant->id]);
    }

    #[Test]
    public function free_plan_price_does_not_create_a_preapproval(): void
    {
        Http::fake();

        $tenant = $this->createTenant();
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 0, 0);

        $subscription = $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1');

        $this->assertNull($subscription->preapproval_id);
        Http::assertNothingSent();
    }

    #[Test]
    public function manual_provider_does_not_create_a_preapproval(): void
    {
        Config::set('services.payments.provider', 'manual');
        Http::fake();

        $tenant = $this->createTenant();
        $plan = $this->createPlan();
        $this->createPlanPrice($plan, 'monthly', 99.90, 0);

        // O provider manual não chama nenhum PSP (é o fallback de
        // dev/homologação sem credenciais), mas o card_token continua
        // sendo exigido no nível do Service para um plano pago — quem
        // liga/desliga o provider real é configuração de ambiente, não
        // uma regra de negócio que o cliente deveria conseguir contornar.
        $subscription = $this->service()->create($tenant->id, $plan->uuid, 'monthly', '127.0.0.1', 'card_token_abc123');

        $this->assertNull($subscription->preapproval_id);
        Http::assertNothingSent();
    }
}
