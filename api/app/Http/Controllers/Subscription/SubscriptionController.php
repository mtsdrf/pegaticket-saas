<?php

namespace App\Http\Controllers\Subscription;

use App\DTOs\Subscription\ChangeSubscriptionPlanDTO;
use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\DTOs\Subscription\UpdateSubscriptionPaymentMethodDTO;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Exceptions\Subscription\CardTokenRequiredException;
use App\Exceptions\Subscription\InvalidInvoicePaymentStateException;
use App\Exceptions\Subscription\NoActivePlanPriceException;
use App\Exceptions\Subscription\NoActivePreapprovalException;
use App\DTOs\Subscription\CancelSubscriptionDTO;
use App\Exceptions\Subscription\InvalidSubscriptionTransitionException;
use App\Exceptions\Subscription\SubscriptionNotFoundException;
use App\Exceptions\Subscription\WithdrawalWindowExpiredException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentResource;
use App\Http\Requests\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Subscription\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionPaymentMethodRequest;
use App\Http\Resources\Subscription\InvoiceResource;
use App\Http\Resources\Subscription\PlanPricingResource;
use App\Http\Resources\Subscription\SubscriptionHistoryResource;
use App\Http\Resources\Subscription\SubscriptionResource;
use App\Models\Plan\Plan;
use App\Models\Subscription\Invoice;
use App\Models\Tenant\Tenant;
use App\Services\APIResponse;
use App\Services\Subscription\InvoiceService;
use App\Services\Subscription\InvoicePaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $service,
        private InvoiceService $invoiceService,
        private InvoicePaymentService $invoicePaymentService,
    ) {
    }

    /**
     * Assinatura atual do tenant + faturas. 200 com data=null quando o
     * tenant ainda não tem assinatura (tela mostra estado vazio).
     */
    public function show()
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::success(null, __('messages.subscription.not_found'));
        }

        $subscription->load(['plan', 'invoices']);

        return APIResponse::success(
            new SubscriptionResource($subscription),
            __('messages.subscription.shown')
        );
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $currentSubscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if ($currentSubscription !== null && $currentSubscription->status !== 'canceled') {
            return APIResponse::error(
                __('messages.subscription.already_exists'),
                422,
                'SUBSCRIPTION_ALREADY_EXISTS'
            );
        }

        $tenant = Tenant::query()
            ->whereKey(app('tenant_id'))
            ->whereNull('deleted_at')
            ->first();

        if ($tenant === null || $tenant->plan_id === null || $tenant->plan === null) {
            return APIResponse::error(
                __('messages.subscription.plan_required'),
                422,
                'SUBSCRIPTION_PLAN_REQUIRED'
            );
        }

        $dto = CreateSubscriptionDTO::fromArray($request->validated());

        // plan_id enviado já foi validado pelo StoreSubscriptionRequest
        // (existe, ativo, não deletado) — quando informado, o Service
        // sincroniza tenants.plan_id antes de criar a assinatura, mesma
        // consistência já garantida por changePlan(). Sem plan_id, mantém o
        // plano que o tenant já tinha (comportamento anterior preservado).
        $planUuid = $dto->planUuid ?? $tenant->plan->uuid;

        try {
            $subscription = $this->service->create(
                $tenant->id,
                $planUuid,
                $dto->billingPeriod,
                (string) $request->ip(),
                $dto->cardToken
            );
        } catch (NoActivePlanPriceException $e) {
            return APIResponse::error($e->getMessage(), 422, 'NO_ACTIVE_PLAN_PRICE');
        } catch (CardTokenRequiredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'SUBSCRIPTION_CARD_TOKEN_REQUIRED');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        $subscription->load(['plan', 'invoices']);

        return APIResponse::success(
            new SubscriptionResource($subscription),
            __('messages.subscription.created'),
            201
        );
    }

    public function cancel(CancelSubscriptionRequest $request)
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::error(__('messages.subscription.not_found'), 404, 'SUBSCRIPTION_NOT_FOUND');
        }

        $dto = CancelSubscriptionDTO::fromArray($request->validated());

        try {
            $this->service->cancel(
                app('tenant_id'),
                $subscription->uuid,
                $dto->immediately,
                $dto->reason
            );
        } catch (SubscriptionNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'SUBSCRIPTION_NOT_FOUND');
        } catch (InvalidSubscriptionTransitionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_SUBSCRIPTION_TRANSITION');
        }

        return APIResponse::success(
            null,
            $dto->immediately
                ? __('messages.subscription.canceled')
                : __('messages.subscription.cancel_scheduled')
        );
    }

    public function withdrawal()
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::error(__('messages.subscription.not_found'), 404, 'SUBSCRIPTION_NOT_FOUND');
        }

        try {
            $refund = $this->service->requestWithdrawal(app('tenant_id'), $subscription->uuid);
        } catch (SubscriptionNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'SUBSCRIPTION_NOT_FOUND');
        } catch (WithdrawalWindowExpiredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'WITHDRAWAL_WINDOW_EXPIRED');
        } catch (InvalidSubscriptionTransitionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_SUBSCRIPTION_TRANSITION');
        }

        return APIResponse::success(
            ['protocol' => $refund->protocol],
            __('messages.subscription.withdrawal_requested')
        );
    }

    /**
     * Reverte um cancelamento agendado (cancel_scheduled -> active).
     */
    public function renew()
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::error(__('messages.subscription.not_found'), 404, 'SUBSCRIPTION_NOT_FOUND');
        }

        try {
            $subscription = $this->service->renew(app('tenant_id'), $subscription->uuid);
        } catch (SubscriptionNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'SUBSCRIPTION_NOT_FOUND');
        } catch (InvalidSubscriptionTransitionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_SUBSCRIPTION_TRANSITION');
        }

        $subscription->load(['plan', 'invoices']);

        return APIResponse::success(
            new SubscriptionResource($subscription),
            __('messages.subscription.renewed')
        );
    }

    /**
     * Troca o cartão da cobrança recorrente automática (Preapproval) já
     * existente.
     */
    public function updatePaymentMethod(UpdateSubscriptionPaymentMethodRequest $request)
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::error(__('messages.subscription.not_found'), 404, 'SUBSCRIPTION_NOT_FOUND');
        }

        $dto = UpdateSubscriptionPaymentMethodDTO::fromArray($request->validated());

        try {
            $subscription = $this->service->updatePaymentMethod(
                app('tenant_id'),
                $subscription->uuid,
                ['token' => $dto->cardToken]
            );
        } catch (SubscriptionNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'SUBSCRIPTION_NOT_FOUND');
        } catch (NoActivePreapprovalException $e) {
            return APIResponse::error($e->getMessage(), 422, 'SUBSCRIPTION_NO_ACTIVE_PREAPPROVAL');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        $subscription->load(['plan', 'invoices']);

        return APIResponse::success(
            new SubscriptionResource($subscription),
            __('messages.subscription.payment_method_updated')
        );
    }

    /**
     * Preço real por período de cobrança + funcionalidades incluídas de UM
     * plano — usado pela tela de contratação para não depender de preço
     * espelhado manualmente no frontend. Sem `?plan_id=`, retorna o preço do
     * plano ATUAL do tenant (comportamento original). Com `?plan_id=`,
     * retorna o preço de QUALQUER plano ativo — usado na primeira
     * contratação, quando o proprietário ainda pode escolher outro plano
     * (Prata/Ouro/Diamante), não só o plan_id padrão do cadastro. Mesma
     * checagem de "tenant sem plano" já usada em store().
     */
    public function planPricing(Request $request)
    {
        $tenant = Tenant::query()
            ->whereKey(app('tenant_id'))
            ->whereNull('deleted_at')
            ->first();

        if ($tenant === null || $tenant->plan_id === null || $tenant->plan === null) {
            return APIResponse::error(
                __('messages.subscription.plan_required'),
                422,
                'SUBSCRIPTION_PLAN_REQUIRED'
            );
        }

        $plan = $tenant->plan;
        $requestedPlanUuid = $request->query('plan_id');

        if ($requestedPlanUuid !== null) {
            $plan = Plan::query()
                ->where('uuid', $requestedPlanUuid)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->first();

            if ($plan === null) {
                return APIResponse::error(
                    __('messages.subscription.plan_not_found'),
                    404,
                    'PLAN_NOT_FOUND'
                );
            }
        }

        $pricing = $this->service->getPlanPricing($plan);

        return APIResponse::success(
            new PlanPricingResource($pricing),
            __('messages.subscription.plan_pricing_shown')
        );
    }

    /**
     * Planos ativos disponíveis com preço real por período — usado pela
     * tela de upgrade/downgrade e pela primeira contratação. Quando o
     * tenant já tem uma assinatura, exclui o plano atual (é troca de
     * verdade). Quando ainda NÃO tem assinatura (primeira contratação),
     * NÃO exclui nada — tenants.plan_id nesse caso é só o padrão do
     * cadastro, não um plano "contratado" de fato.
     */
    public function availablePlans()
    {
        $tenant = Tenant::query()
            ->whereKey(app('tenant_id'))
            ->whereNull('deleted_at')
            ->first();

        if ($tenant === null || $tenant->plan_id === null) {
            return APIResponse::error(
                __('messages.subscription.plan_required'),
                422,
                'SUBSCRIPTION_PLAN_REQUIRED'
            );
        }

        $currentSubscription = $this->service->findCurrentForTenant(app('tenant_id'));
        $hasSubscription = $currentSubscription !== null && $currentSubscription->status !== 'canceled';

        $plans = $this->service->listAvailablePlans((int) $tenant->plan_id, $hasSubscription);

        return APIResponse::success(
            PlanPricingResource::collection($plans),
            __('messages.subscription.available_plans_shown')
        );
    }

    /**
     * Troca de plano de uma assinatura já existente (upgrade/downgrade).
     */
    public function changePlan(ChangeSubscriptionPlanRequest $request)
    {
        $subscription = $this->service->findCurrentForTenant(app('tenant_id'));

        if (! $subscription) {
            return APIResponse::error(__('messages.subscription.not_found'), 404, 'SUBSCRIPTION_NOT_FOUND');
        }

        $dto = ChangeSubscriptionPlanDTO::fromArray($request->validated());

        try {
            $subscription = $this->service->changePlan(
                app('tenant_id'),
                $subscription->uuid,
                $dto->planUuid,
                $dto->billingPeriod,
                $dto->cardToken
            );
        } catch (SubscriptionNotFoundException $e) {
            return APIResponse::error($e->getMessage(), 404, 'SUBSCRIPTION_NOT_FOUND');
        } catch (InvalidSubscriptionTransitionException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_SUBSCRIPTION_TRANSITION');
        } catch (NoActivePlanPriceException $e) {
            return APIResponse::error($e->getMessage(), 422, 'NO_ACTIVE_PLAN_PRICE');
        } catch (CardTokenRequiredException $e) {
            return APIResponse::error($e->getMessage(), 422, 'SUBSCRIPTION_CARD_TOKEN_REQUIRED');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new SubscriptionResource($subscription),
            __('messages.subscription.plan_changed')
        );
    }

    /**
     * Histórico paginado de TODAS as assinaturas do tenant ao longo do
     * tempo (não só a atual).
     */
    public function history(Request $request)
    {
        $list = $this->service->listHistoryForTenant(app('tenant_id'), (int) $request->get('per_page', 15));

        return APIResponse::success(
            SubscriptionHistoryResource::collection($list),
            __('messages.subscription.history_list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    /**
     * Histórico paginado de faturas do tenant (todas as assinaturas dele ao
     * longo do tempo, não só a atual).
     */
    public function invoices(Request $request)
    {
        $list = $this->invoiceService->listForTenant(app('tenant_id'), (int) $request->get('per_page', 15));

        return APIResponse::success(
            InvoiceResource::collection($list),
            __('messages.subscription.invoices_list'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
            ]
        );
    }

    public function invoicePixCharge(Invoice $invoice)
    {
        try {
            $payment = $this->invoicePaymentService->createOrReusePixCharge($invoice);
        } catch (InvalidInvoicePaymentStateException $e) {
            return APIResponse::error($e->getMessage(), 422, 'INVALID_INVOICE_PAYMENT_STATE');
        } catch (PaymentOperationInProgressException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_OPERATION_IN_PROGRESS');
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 422, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success(
            new PaymentResource($payment),
            __('messages.subscription.invoice_payment_charge_created'),
            201
        );
    }
}
