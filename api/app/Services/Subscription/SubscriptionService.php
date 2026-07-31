<?php

namespace App\Services\Subscription;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\Subscription\SubscriptionStatus;
use App\Events\Subscription\SubscriptionCanceled;
use App\Events\Subscription\SubscriptionCreated;
use App\Events\Subscription\SubscriptionPlanChanged;
use App\Events\Subscription\SubscriptionWithdrawalRequested;
use App\Exceptions\Subscription\CardTokenRequiredException;
use App\Exceptions\Subscription\InvalidSubscriptionTransitionException;
use App\Exceptions\Subscription\NoActivePlanPriceException;
use App\Exceptions\Subscription\NoActivePreapprovalException;
use App\Exceptions\Subscription\SubscriptionNotFoundException;
use App\Exceptions\Subscription\WithdrawalWindowExpiredException;
use App\Models\Plan\Plan;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Models\Subscription\Subscription;
use App\Models\Subscription\SubscriptionEvent;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\PlanPriceRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Logging\ApplicationLogger;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de negócio de assinatura (roadmap 1B). Trial de 14 dias, aceite de
 * termos congelado, cancelamento imediato/fim-de-ciclo e arrependimento de
 * 7 dias (gera Refund). Transições de status passam pela
 * SubscriptionStateMachine.
 */
class SubscriptionService
{
    /**
     * Dias da janela legal de arrependimento (CDC art. 49).
     */
    private const WITHDRAWAL_WINDOW_DAYS = 7;

    /**
     * Dias de trial concedidos na criação da assinatura.
     */
    private const TRIAL_DAYS = 14;

    /**
     * Dias de tolerância após falha de cobrança do Mercado Pago (Preapproval)
     * antes da suspensão total de acesso. Decisão de produto confirmada
     * (roadmap Fase B, item 1 — assinatura).
     */
    private const GRACE_PERIOD_DAYS = 7;

    /**
     * billing_period -> meses do ciclo, usado para avançar
     * current_period_start/end após confirmação de pagamento do ciclo.
     * Mesmo mapa do GenerateSubscriptionInvoicesCommand (fluxo do provider
     * manual); aqui alimenta o fluxo dirigido pelo webhook mercadopago.
     */
    private const PERIOD_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'yearly' => 12,
    ];

    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private PlanPriceRepositoryInterface $planPriceRepository,
        private SubscriptionStateMachine $stateMachine,
        private InvoiceService $invoiceService,
        private PaymentProviderInterface $paymentProvider
    ) {
    }

    public function findCurrentForTenant(int $tenantId): ?Subscription
    {
        return $this->repository->findCurrentForTenant($tenantId);
    }

    /**
     * Cria a primeira assinatura do tenant. `$planUuid` pode ser o plano que
     * o tenant já tinha fixado em `tenants.plan_id` (padrão do cadastro) OU
     * um plano diferente escolhido pelo proprietário nesta primeira
     * contratação — em ambos os casos, `tenants.plan_id` é (re)sincronizado
     * com o plano efetivamente contratado dentro da mesma transação, mesma
     * consistência já garantida por changePlan() (fonte de verdade das
     * funcionalidades liberadas via
     * PermissionService::resolveTenantAllowedFunctionalities).
     */
    public function create(int $tenantId, string $planUuid, string $billingPeriod, string $termsIp, ?string $cardToken = null): Subscription
    {
        $plan = Plan::where('uuid', $planUuid)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->firstOrFail();

        $planPrice = $this->planPriceRepository->findActive($plan->id, $billingPeriod);

        if (! $planPrice) {
            throw new NoActivePlanPriceException(__('messages.subscription.no_active_price'));
        }

        $activeTermsVersion = DB::table('legal_documents')
            ->where('type', 'terms')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->value('version');

        $now = now();
        $trialEnds = $now->copy()->addDays(self::TRIAL_DAYS);

        // uuid gerado ANTES de qualquer persistência — é ele que vira o
        // external_reference enviado ao Mercado Pago (quando o plano é
        // pago) e o mesmo valor é usado para criar a Subscription depois.
        $subscriptionUuid = (string) Str::uuid();

        $preapproval = null;

        // Plano gratuito (amount 0 no período contratado) não gera
        // Preapproval — não há sinal dedicado de "plano free" no model
        // Plan hoje, então o preço vigente é a fonte da verdade (mesmo
        // critério já usado para calcular a fatura em generateForCycle).
        if (Money::toMinor((string) $planPrice->amount) > 0) {
            // Decisão de produto (2026-07-24): a contratação nunca mais
            // redireciona ao checkout do Mercado Pago — o cartão é sempre
            // tokenizado no navegador (MP.js) e chega aqui já como token.
            // Falha explícita ANTES de qualquer chamada ao PSP; nunca cria
            // um Preapproval `pending` sem cartão associado.
            if ($cardToken === null || $cardToken === '') {
                throw new CardTokenRequiredException(__('messages.subscription.card_token_required'));
            }

            // Chama o PSP ANTES de abrir a transação que persiste a
            // Subscription. O lock de idempotência persistido
            // (MercadoPagoPaymentProvider::acquireIdempotency, operação
            // "preapproval_create:{uuid}") sobrevive mesmo que esta
            // chamada dê timeout; se a Subscription fosse criada primeiro
            // dentro da mesma transação que chama o PSP, um timeout
            // reverteria a transação inteira (Subscription E o próprio
            // registro de idempotência, se estivesse na mesma transação),
            // deixando o próximo clique livre para gerar uma chave nova —
            // exatamente o bug que este fluxo corrige.
            $transientSubscription = new Subscription([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'billing_period' => $billingPeriod,
                'trial_ends_at' => $trialEnds,
            ]);
            $transientSubscription->uuid = $subscriptionUuid;
            $transientSubscription->setRelation('plan', $plan);

            $preapproval = $this->paymentProvider->createPreapproval($transientSubscription, 'preapproval_create', $cardToken);
        }

        return DB::transaction(function () use (
            $tenantId,
            $plan,
            $planPrice,
            $billingPeriod,
            $termsIp,
            $activeTermsVersion,
            $now,
            $trialEnds,
            $subscriptionUuid,
            $preapproval
        ) {
            $preapprovalId = ! empty($preapproval['preapproval_id'] ?? null) ? $preapproval['preapproval_id'] : null;

            $subscription = $this->repository->create([
                'uuid' => $subscriptionUuid,
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'billing_period' => $billingPeriod,
                'status' => SubscriptionStatus::Trialing->value,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => $now,
                'current_period_end' => $trialEnds,
                'next_charge_at' => $trialEnds,
                'auto_renew' => true,
                'accepted_terms_version' => $activeTermsVersion,
                'accepted_at' => $now,
                'accepted_ip' => $termsIp,
                'preapproval_id' => $preapprovalId,
            ]);

            // Sincroniza tenants.plan_id com o plano efetivamente contratado
            // (idempotente quando já era o mesmo plano padrão do cadastro).
            Tenant::whereKey($tenantId)->update(['plan_id' => $plan->id]);

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'type' => 'created',
                'payload' => ['status' => SubscriptionStatus::Trialing->value, 'trial_ends_at' => $trialEnds->toIso8601String()],
                'created_at' => $now,
            ]);

            if ($preapprovalId !== null) {
                $subscription->setAttribute('checkout_url', $preapproval['checkout_url'] ?? null);

                SubscriptionEvent::create([
                    'subscription_id' => $subscription->id,
                    'type' => 'preapproval_created',
                    'payload' => [
                        'preapproval_id' => $preapprovalId,
                        'checkout_url' => $preapproval['checkout_url'] ?? null,
                    ],
                    'created_at' => now(),
                ]);
            }

            event(new SubscriptionCreated(subscriptionUuid: $subscription->uuid, actorId: Auth::id()));

            return $subscription;
        });
    }

    /**
     * Estados em que a assinatura pode trocar de plano. Deliberadamente
     * exclui `cancel_scheduled`/`suspended`/`canceled` — um cancelamento
     * agendado precisa ser revertido via renew() primeiro (evita misturar
     * duas intenções na mesma chamada).
     */
    private const CHANGE_PLAN_ALLOWED_STATUSES = ['trialing', 'active', 'past_due'];

    /**
     * Troca de plano de uma assinatura já existente (upgrade/downgrade).
     *
     * Estratégia de Preapproval (Mercado Pago) — decisão registrada em
     * architecture-decisions.md: a doc oficial já consultada não confirma
     * que `PUT /preapproval/{id}` aceite atualizar `auto_recurring.
     * transaction_amount`/`reason` para trocar de plano (só documenta troca
     * de cartão/pausa/cancelamento); por segurança, o Maskats NUNCA envia
     * um campo de payload não confirmado pela documentação. A troca é
     * sempre CRIAR o novo Preapproval primeiro e só então CANCELAR o antigo
     * (nesta ordem, nunca o inverso): se a criação do novo falhar, a
     * transação local é revertida por inteiro e nenhum efeito remoto
     * chegou a ocorrer (o antigo Preapproval continua intacto); se a
     * criação for bem-sucedida mas o cancelamento do antigo falhar
     * (melhor esforço, como já em cancel()), o tenant nunca fica sem
     * cobrança recorrente ativa — o pior caso é ficar com dois Preapprovals
     * simultâneos por um tempo, mitigado por log de erro + o comando de
     * reconciliação já existente (ReconcileMercadoPagoSubscriptionsCommand).
     *
     * Sem rateio/proporcionalidade: current_period_start/end e
     * trial_ends_at NÃO são recalculados aqui — o novo valor só passa a ser
     * cobrado a partir do próximo ciclo (mesmo start_date já em vigor).
     * Nenhum crédito é calculado sobre o valor já pago no plano anterior.
     * Decisão de engenharia simples, sinalizada como pergunta em aberto
     * (rateio proporcional é decisão comercial/jurídica, não código) em
     * architecture-decisions.md.
     */
    public function changePlan(int $tenantId, string $subscriptionUuid, string $planUuid, string $billingPeriod, ?string $cardToken = null): Subscription
    {
        $subscription = $this->resolveOwned($tenantId, $subscriptionUuid);

        $plan = Plan::where('uuid', $planUuid)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->firstOrFail();

        $planPrice = $this->planPriceRepository->findActive($plan->id, $billingPeriod);

        if (! $planPrice) {
            throw new NoActivePlanPriceException(__('messages.subscription.no_active_price'));
        }

        // Fase 1 (transação curta, SEM chamar o PSP): valida a transição
        // sob lock e prepara os novos atributos em memória. A transação
        // fecha aqui de propósito — a proteção real contra corrida/duplo-
        // clique na chamada ao PSP passa a ser o lock de idempotência
        // persistido (MercadoPagoPaymentProvider::acquireIdempotency,
        // operação "preapproval_change:{uuid}"), que sobrevive mesmo se a
        // transação de negócio reverter por causa de um timeout do PSP —
        // diferente do lockForUpdate, que seria liberado pelo rollback.
        [$transientSubscription, $previousPreapprovalId, $isNewPlanPaid] = DB::transaction(
            function () use ($subscription, $plan, $planPrice, $billingPeriod) {
                $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, self::CHANGE_PLAN_ALLOWED_STATUSES, true)) {
                    throw new InvalidSubscriptionTransitionException(
                        __('messages.subscription.change_plan_not_allowed')
                    );
                }

                if ((int) $locked->plan_id === $plan->id && $locked->billing_period === $billingPeriod) {
                    throw new InvalidSubscriptionTransitionException(
                        __('messages.subscription.plan_unchanged')
                    );
                }

                $previousPreapprovalId = $locked->preapproval_id;
                $isNewPlanPaid = Money::toMinor((string) $planPrice->amount) > 0;

                // Muda os atributos de plano em memória (não salvos ainda)
                // ANTES de chamar o PSP — createPreapproval() lê
                // plan_price_id/billing_period/uuid do próprio objeto
                // (mesmo padrão de create()).
                $locked->fill([
                    'plan_id' => $plan->id,
                    'plan_price_id' => $planPrice->id,
                    'billing_period' => $billingPeriod,
                ]);

                return [$locked, $previousPreapprovalId, $isNewPlanPaid];
            }
        );

        // Decisão de produto (2026-07-24): a troca de plano nunca mais
        // redireciona ao checkout do Mercado Pago — falha explícita ANTES
        // de qualquer chamada ao PSP quando o novo plano é pago e nenhum
        // cartão tokenizado foi enviado.
        if ($isNewPlanPaid && ($cardToken === null || $cardToken === '')) {
            throw new CardTokenRequiredException(__('messages.subscription.card_token_required'));
        }

        $newPreapprovalId = null;
        $checkoutUrl = null;

        if ($isNewPlanPaid) {
            $preapproval = $this->paymentProvider->createPreapproval($transientSubscription, 'preapproval_change', $cardToken);
            $newPreapprovalId = ! empty($preapproval['preapproval_id']) ? $preapproval['preapproval_id'] : null;
            $checkoutUrl = $preapproval['checkout_url'] ?? null;
        }

        if ($previousPreapprovalId !== null) {
            // $transientSubscription->preapproval_id ainda é o ANTIGO neste
            // ponto — cancelPreapproval() lê esse atributo. Falha aqui é só
            // logada (mesmo padrão de cancel()): nunca desfaz a troca já
            // decidida.
            try {
                $this->paymentProvider->cancelPreapproval($transientSubscription);
            } catch (\Throwable $e) {
                ApplicationLogger::error('Falha ao cancelar o Preapproval anterior na troca de plano', [
                    'subscription_uuid' => $transientSubscription->uuid,
                    'previous_preapproval_id' => $previousPreapprovalId,
                ]);
            }
        }

        // Fase 2: persiste o resultado sob lock novamente (mesmo padrão de
        // requestWithdrawal/renew — reconferência defensiva dentro da
        // transação que efetivamente grava o estado).
        return DB::transaction(function () use (
            $subscription,
            $tenantId,
            $plan,
            $planPrice,
            $billingPeriod,
            $newPreapprovalId,
            $checkoutUrl,
            $previousPreapprovalId
        ) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $locked->fill([
                'plan_id' => $plan->id,
                'plan_price_id' => $planPrice->id,
                'billing_period' => $billingPeriod,
                'preapproval_id' => $newPreapprovalId,
            ]);
            $locked->save();

            if ($newPreapprovalId !== null) {
                SubscriptionEvent::create([
                    'subscription_id' => $locked->id,
                    'type' => 'preapproval_created',
                    'payload' => ['preapproval_id' => $newPreapprovalId, 'reason' => 'plan_changed'],
                    'created_at' => now(),
                ]);
            }

            // tenants.plan_id é a fonte de verdade das funcionalidades
            // liberadas (PermissionService::resolveTenantAllowedFunctionalities)
            // — sem isso, o tenant continuaria com o acesso do plano
            // anterior mesmo após a troca.
            Tenant::whereKey($tenantId)->update(['plan_id' => $plan->id]);

            SubscriptionEvent::create([
                'subscription_id' => $locked->id,
                'type' => 'plan_changed',
                'payload' => [
                    'plan_id' => $plan->id,
                    'billing_period' => $billingPeriod,
                    'previous_preapproval_id' => $previousPreapprovalId,
                    'new_preapproval_id' => $newPreapprovalId,
                ],
                'created_at' => now(),
            ]);

            event(new SubscriptionPlanChanged(subscriptionUuid: $locked->uuid, actorId: Auth::id()));

            if ($checkoutUrl !== null) {
                $locked->setAttribute('checkout_url', $checkoutUrl);
            }

            // load() em vez de fresh(): preserva o atributo dinâmico
            // checkout_url setado acima (fresh() traria uma instância nova
            // só com o que está persistido no banco).
            $locked->load(['plan', 'invoices']);

            return $locked;
        });
    }

    /**
     * Todos os planos ativos com preço real por período e funcionalidades —
     * usado pela tela de upgrade/downgrade e pela primeira contratação.
     * Reaproveita getPlanPricing() (mesma fórmula usada para cobrar de
     * fato), sem duplicar o cálculo por plano.
     *
     * `$excludeCurrentPlan` (default true, comportamento original): quando
     * o tenant já tem uma assinatura, o plano atual é excluído (é troca de
     * verdade). Na primeira contratação (sem assinatura ainda), o
     * controller passa `false` — `tenants.plan_id` nesse caso é só o padrão
     * do cadastro, não um plano "contratado" de fato, então não faz sentido
     * escondê-lo da lista de opções.
     *
     * @return list<array{
     *     plan: array{uuid: string, name: string, slug: string, description: ?string},
     *     billing_periods: list<array{billing_period: string, months: int, discount_percent: float, list_amount: string, total_amount: string, monthly_equivalent: string}>,
     *     functionalities: list<array{uuid: string, name: string, slug: string, description: ?string}>
     * }>
     */
    public function listAvailablePlans(int $currentPlanId, bool $excludeCurrentPlan = true): array
    {
        return Plan::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($excludeCurrentPlan, fn ($query) => $query->where('id', '!=', $currentPlanId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => $this->getPlanPricing($plan))
            ->values()
            ->all();
    }

    /**
     * Histórico paginado de TODAS as assinaturas do tenant ao longo do
     * tempo (não só a atual).
     */
    public function listHistoryForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateForTenant($tenantId, $perPage);
    }

    public function cancel(int $tenantId, string $subscriptionUuid, bool $immediately, ?string $reason): void
    {
        $subscription = $this->resolveOwned($tenantId, $subscriptionUuid);

        DB::transaction(function () use ($subscription, $immediately, $reason) {
            if ($immediately) {
                $this->stateMachine->transition(
                    $subscription,
                    SubscriptionStatus::Canceled,
                    ['reason' => $reason, 'immediately' => true]
                );

                $subscription->fill([
                    'canceled_at' => now(),
                    'auto_renew' => false,
                ]);
                $subscription->save();

                if ($subscription->preapproval_id !== null) {
                    // Cancela a cobrança recorrente no PSP para não deixar
                    // o Mercado Pago cobrando uma assinatura já encerrada
                    // aqui. Falha aqui não desfaz o cancelamento local (o
                    // dono já pediu para cancelar); só é logada.
                    try {
                        $this->paymentProvider->cancelPreapproval($subscription);
                    } catch (\Throwable $e) {
                        ApplicationLogger::error('Falha ao cancelar Preapproval no PSP', [
                            'subscription_uuid' => $subscription->uuid,
                        ]);
                    }
                }
            } else {
                $this->stateMachine->transition(
                    $subscription,
                    SubscriptionStatus::CancelScheduled,
                    ['reason' => $reason, 'immediately' => false]
                );

                $subscription->fill([
                    'cancel_at' => $subscription->current_period_end ?? $subscription->next_charge_at,
                    'auto_renew' => false,
                ]);
                $subscription->save();
            }

            event(new SubscriptionCanceled(
                subscriptionUuid: $subscription->uuid,
                immediately: $immediately,
                actorId: Auth::id()
            ));
        });
    }

    public function requestWithdrawal(int $tenantId, string $subscriptionUuid): Refund
    {
        $subscription = $this->resolveOwned($tenantId, $subscriptionUuid);

        if ($subscription->created_at->lt(now()->subDays(self::WITHDRAWAL_WINDOW_DAYS))) {
            throw new WithdrawalWindowExpiredException(__('messages.subscription.withdrawal_window_expired'));
        }

        return DB::transaction(function () use ($subscription) {
            // lockForUpdate + reconferência do status DENTRO da transação:
            // achado de auditoria — sem isso, um duplo-clique/replay de rede
            // no endpoint de arrependimento (a assinatura já cancelada
            // continua sendo encontrada por findCurrentForTenant, que não
            // filtra por status) criava um SEGUNDO Refund local e disparava
            // um SEGUNDO estorno real no PSP a cada nova chamada (o dinheiro
            // fica protegido pela chave de idempotência determinística do
            // adapter, mas o histórico local duplicava indevidamente).
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status === SubscriptionStatus::Canceled->value) {
                throw new InvalidSubscriptionTransitionException(
                    __('messages.subscription.withdrawal_already_processed')
                );
            }

            // Último pagamento (se houver) das faturas da assinatura — pode
            // não existir se ainda em trial. amount espelha esse pagamento
            // ou 0 quando nada foi cobrado.
            $payment = Payment::query()
                ->whereNull('deleted_at')
                ->where('payable_type', Invoice::class)
                ->whereIn('payable_id', $subscription->invoices()->pluck('id'))
                ->orderByDesc('id')
                ->first();

            $refund = Refund::create([
                'tenant_id' => $subscription->tenant_id,
                'payment_id' => $payment?->id,
                'reason' => 'Arrependimento (7 dias)',
                'amount' => $payment ? Money::normalize((string) $payment->amount) : Money::normalize(0),
                'type' => 'total',
                'requested_by' => Auth::id(),
                'protocol' => $this->generateProtocol(),
                'status' => 'requested',
            ]);

            if ($payment !== null && $payment->provider !== 'manual' && $payment->provider_charge_id !== null) {
                try {
                    $providerRefund = $this->paymentProvider->refund($payment);

                    $refund->fill([
                        'provider_refund_id' => $providerRefund['provider_refund_id'] ?? null,
                        'status' => (string) ($providerRefund['status'] ?? 'requested'),
                        'amount' => $providerRefund['amount'] ?? $refund->amount,
                    ]);
                    $refund->save();
                } catch (\Throwable $e) {
                    $refund->fill(['status' => 'failed']);
                    $refund->save();

                    ApplicationLogger::error('Falha ao solicitar estorno no PSP para arrependimento de assinatura', [
                        'subscription_uuid' => $subscription->uuid,
                        'payment_uuid' => $payment->uuid,
                    ]);
                }
            }

            // Arrependimento cancela a assinatura imediatamente (se ainda não
            // estiver em estado terminal).
            if ($subscription->status !== SubscriptionStatus::Canceled->value) {
                $this->stateMachine->transition(
                    $subscription,
                    SubscriptionStatus::Canceled,
                    ['reason' => 'withdrawal', 'refund_protocol' => $refund->protocol]
                );

                $subscription->fill(['canceled_at' => now(), 'auto_renew' => false]);
                $subscription->save();
            }

            event(new SubscriptionWithdrawalRequested(
                subscriptionUuid: $subscription->uuid,
                refundProtocol: $refund->protocol,
                actorId: Auth::id()
            ));

            return $refund;
        });
    }

    /**
     * Reverte um cancelamento agendado (`cancel_scheduled -> active`). É a
     * única forma de "renovar" já suportada pela máquina de estados hoje —
     * um cancelamento agendado ainda não desliga o Preapproval no Mercado
     * Pago (só o cancelamento IMEDIATO chama cancelPreapproval), então
     * reverter é 100% local: limpa `cancel_at`, volta `auto_renew=true` e
     * transiciona o status de volta para `active`. Lock pessimista +
     * reconferência do status dentro da transação (mesmo padrão de
     * requestWithdrawal) protege contra duplo-clique/replay.
     */
    public function renew(int $tenantId, string $subscriptionUuid): Subscription
    {
        $subscription = $this->resolveOwned($tenantId, $subscriptionUuid);

        return DB::transaction(function () use ($subscription) {
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status !== SubscriptionStatus::CancelScheduled->value) {
                throw new InvalidSubscriptionTransitionException(
                    __('messages.subscription.renew_not_allowed')
                );
            }

            $this->stateMachine->transition($subscription, SubscriptionStatus::Active, [
                'reason' => 'renewed_by_owner',
            ]);

            $subscription->fill(['cancel_at' => null, 'auto_renew' => true]);
            $subscription->save();

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'type' => 'renewed',
                'payload' => [],
                'created_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Troca o cartão da cobrança recorrente automática (Preapproval) já
     * existente. Nunca recebe dado de cartão cru — $cardToken['token'] já
     * foi tokenizado pelo MP.js no frontend (mesmo padrão de
     * createCardCharge). Exige um Preapproval ativo (plano pago, provider
     * mercadopago); plano gratuito/provider manual não têm o que trocar.
     *
     * @param array<string, mixed> $cardToken
     */
    public function updatePaymentMethod(int $tenantId, string $subscriptionUuid, array $cardToken): Subscription
    {
        $subscription = $this->resolveOwned($tenantId, $subscriptionUuid);

        if ($subscription->preapproval_id === null) {
            throw new NoActivePreapprovalException(__('messages.subscription.no_active_preapproval'));
        }

        return DB::transaction(function () use ($subscription, $cardToken) {
            $result = $this->paymentProvider->updatePreapprovalPaymentMethod($subscription, $cardToken);

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'type' => 'payment_method_updated',
                'payload' => ['status' => $result['status'] ?? null],
                'created_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Confirmação de cobrança do ciclo vinda do webhook de Preapproval
     * (`subscription_authorized_payment` aprovado). Gera/localiza a fatura
     * do ciclo corrente, marca como paga, encerra período de graça (se
     * houver) e avança current_period_start/end/next_charge_at.
     * Idempotente: chamado só quando o PaymentWebhookController resolve um
     * evento novo (webhook_events já filtra reentrega), mas também é seguro
     * chamar 2x — a fatura não é duplicada (findOrCreateForCurrentCycle) e
     * o avanço de período usa sempre current_period_end como base, então
     * uma segunda chamada no mesmo instante avança a partir do novo período
     * (não duplica o avanço porque o próximo pagamento só chega depois).
     */
    public function confirmCyclePayment(Subscription $subscription, string|int|float $amountPaid): void
    {
        DB::transaction(function () use ($subscription, $amountPaid) {
            $invoice = $this->invoiceService->findOrCreateForCurrentCycle($subscription);
            $confirmed = $this->invoiceService->markPaidFromWebhook($invoice, $amountPaid);

            if (! $confirmed) {
                // Valor reportado pelo Mercado Pago diverge do esperado —
                // nunca confia no webhook sozinho. Não avança período nem
                // reativa a assinatura; fica marcado para reconciliação
                // manual (fatura `divergent`).
                ApplicationLogger::error('Valor divergente na cobrança de ciclo do Mercado Pago', [
                    'subscription_uuid' => $subscription->uuid,
                    'invoice_uuid' => $invoice->uuid,
                ]);

                SubscriptionEvent::create([
                    'subscription_id' => $subscription->id,
                    'type' => 'cycle_payment_divergent',
                    'payload' => ['invoice_uuid' => $invoice->uuid],
                    'created_at' => now(),
                ]);

                return;
            }

            $wasBlocked = in_array($subscription->status, ['past_due', 'suspended'], true);

            $subscription->fill(['grace_period_ends_at' => null]);

            $months = self::PERIOD_MONTHS[$subscription->billing_period] ?? 1;
            $newStart = $subscription->current_period_end ?? now();
            $newEnd = $newStart->copy()->addMonths($months);

            $subscription->fill([
                'current_period_start' => $newStart,
                'current_period_end' => $newEnd,
                'next_charge_at' => $newEnd,
            ]);
            $subscription->save();

            $currentStatus = SubscriptionStatus::from($subscription->status);
            if ($wasBlocked && $this->stateMachine->canTransition($currentStatus, SubscriptionStatus::Active)) {
                $this->stateMachine->transition($subscription, SubscriptionStatus::Active, [
                    'reason' => 'cycle_payment_confirmed',
                ]);
            } elseif ($currentStatus === SubscriptionStatus::Trialing
                && $this->stateMachine->canTransition($currentStatus, SubscriptionStatus::Active)
            ) {
                $this->stateMachine->transition($subscription, SubscriptionStatus::Active, [
                    'reason' => 'cycle_payment_confirmed',
                ]);
            }

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'type' => 'cycle_paid',
                'payload' => ['amount' => $amountPaid, 'invoice_uuid' => $invoice->uuid],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Falha de cobrança do ciclo vinda do webhook de Preapproval
     * (`subscription_authorized_payment` rejeitado/cancelado). Inicia a
     * tolerância de 7 dias — idempotente: se já houver
     * `grace_period_ends_at` setado, não reinicia a contagem nem
     * re-dispara o evento (proteção contra reentrega duplicada do PSP).
     */
    public function startGracePeriod(Subscription $subscription): void
    {
        if ($subscription->grace_period_ends_at !== null) {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $subscription->fill(['grace_period_ends_at' => now()->addDays(self::GRACE_PERIOD_DAYS)]);
            $subscription->save();

            $status = SubscriptionStatus::from($subscription->status);
            if ($this->stateMachine->canTransition($status, SubscriptionStatus::PastDue)) {
                $this->stateMachine->transition($subscription, SubscriptionStatus::PastDue, [
                    'reason' => 'cycle_payment_failed',
                    'grace_period_ends_at' => $subscription->grace_period_ends_at->toIso8601String(),
                ]);
            } else {
                SubscriptionEvent::create([
                    'subscription_id' => $subscription->id,
                    'type' => 'grace_period_started',
                    'payload' => ['grace_period_ends_at' => $subscription->grace_period_ends_at->toIso8601String()],
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reconciliação ativa do vínculo recorrente (`preapproval`) consultado
     * diretamente no Mercado Pago.
     *
     * Regras conservadoras:
     * - remoto `cancelled` encerra localmente a assinatura se ela ainda não
     *   estiver cancelada;
     * - remoto `authorized` destrava assinatura local `pending` para
     *   `active`;
     * - demais estados são apenas observáveis no momento e não forçam
     *   transição local sem evidência melhor.
     */
    public function reconcilePreapprovalStatus(Subscription $subscription, string $remoteStatus): Subscription
    {
        return DB::transaction(function () use ($subscription, $remoteStatus) {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = SubscriptionStatus::from($subscription->status);

            if ($remoteStatus === 'cancelled' && $currentStatus !== SubscriptionStatus::Canceled) {
                if ($this->stateMachine->canTransition($currentStatus, SubscriptionStatus::Canceled)) {
                    $this->stateMachine->transition($subscription, SubscriptionStatus::Canceled, [
                        'source' => 'mercadopago_preapproval_reconciliation',
                        'remote_status' => $remoteStatus,
                    ]);
                }

                $subscription->fill([
                    'canceled_at' => now(),
                    'auto_renew' => false,
                ]);
                $subscription->save();

                return $subscription->fresh();
            }

            if ($remoteStatus === 'authorized' && $currentStatus === SubscriptionStatus::Pending) {
                if ($this->stateMachine->canTransition($currentStatus, SubscriptionStatus::Active)) {
                    $this->stateMachine->transition($subscription, SubscriptionStatus::Active, [
                        'source' => 'mercadopago_preapproval_reconciliation',
                        'remote_status' => $remoteStatus,
                    ]);
                }

                return $subscription->fresh();
            }

            return $subscription;
        });
    }

    /**
     * Suspende assinaturas cuja tolerância de 7 dias já venceu sem
     * regularização (usado por `subscriptions:enforce-grace-period`).
     * Idempotente por natureza: a query do repositório já exclui quem já
     * está `suspended`/`canceled`.
     */
    public function suspendExpiredGracePeriod(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $status = SubscriptionStatus::from($subscription->status);

            if (! $this->stateMachine->canTransition($status, SubscriptionStatus::Suspended)) {
                return;
            }

            $this->stateMachine->transition($subscription, SubscriptionStatus::Suspended, [
                'reason' => 'grace_period_expired',
            ]);
        });
    }

    /**
     * Preço real por período de cobrança do plano ATUAL do tenant + lista
     * de funcionalidades incluídas — usado pela tela de contratação
     * ("Empresa" do proprietário) para não depender de preço espelhado
     * manualmente no frontend. Reaproveita `Money::applyDiscount` (mesma
     * fórmula de `InvoiceService::generateForCycle`) e `PlanPriceRepository
     * ::findActive` (mesma fonte usada para efetivamente cobrar).
     *
     * @return array{
     *     plan: array{uuid: string, name: string, slug: string, description: ?string},
     *     billing_periods: list<array{billing_period: string, months: int, discount_percent: float, list_amount: string, total_amount: string, monthly_equivalent: string}>,
     *     functionalities: list<array{uuid: string, name: string, slug: string, description: ?string}>
     * }
     */
    public function getPlanPricing(Plan $plan): array
    {
        $billingPeriods = [];

        foreach (self::PERIOD_MONTHS as $period => $months) {
            $planPrice = $this->planPriceRepository->findActive($plan->id, $period);

            if ($planPrice === null) {
                continue;
            }

            $breakdown = Money::applyDiscount($planPrice->amount, $planPrice->discount_percent);

            $billingPeriods[] = [
                'billing_period' => $period,
                'months' => $months,
                'discount_percent' => (float) $planPrice->discount_percent,
                'list_amount' => $breakdown['gross'],
                'total_amount' => $breakdown['net'],
                'monthly_equivalent' => Money::normalize(Money::toMinor($breakdown['net']) / $months / 100),
            ];
        }

        $functionalities = $plan->functionalities()
            ->get(['functionalities.uuid', 'functionalities.name', 'functionalities.slug', 'functionalities.description'])
            ->map(fn ($functionality) => [
                'uuid' => $functionality->uuid,
                'name' => $functionality->name,
                'slug' => $functionality->slug,
                'description' => $functionality->description,
            ])
            ->all();

        return [
            'plan' => [
                'uuid' => $plan->uuid,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
            ],
            'billing_periods' => $billingPeriods,
            'functionalities' => $functionalities,
        ];
    }

    private function resolveOwned(int $tenantId, string $subscriptionUuid): Subscription
    {
        $subscription = $this->repository->findByUuidForTenant($subscriptionUuid, $tenantId);

        if (! $subscription) {
            throw new SubscriptionNotFoundException(__('messages.subscription.not_found'));
        }

        return $subscription;
    }

    private function generateProtocol(): string
    {
        return 'REF-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
