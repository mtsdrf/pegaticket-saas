<?php

namespace App\Http\Controllers\Subscription;

use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Models\Subscription\WebhookEvent;
use App\Services\APIResponse;
use App\Services\Logging\ApplicationLogger;
use App\Services\Order\OrderPaymentService;
use App\Services\Payment\MercadoPagoPaymentProvider;
use App\Services\Subscription\InvoicePaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;

/**
 * Webhook de pagamento (roadmap 1B, ligado ao PSP real na Fase B item 1). A
 * rota é 100% pública (fora de jwt/tenant/perm) — o PSP chama aqui direto.
 * Idempotência por (provider, external_id) via `webhook_events` (unique
 * composto) para todo provider, sempre a primeira coisa feita.
 *
 * Providers sem adapter real (ex.: 'manual', 'asaas') continuam com o
 * comportamento original: apenas registram o evento e respondem 501 — não
 * há assinatura pra validar nem processamento possível sem PSP.
 *
 * 'mercadopago' é o único provider com validação de assinatura real
 * (x-signature HMAC) e processamento efetivo: o ramo de PEDIDO (cliente
 * final → tenant, API de Orders — `type=order`, `data.id` é o id da order,
 * ex. "ORD01JQ...") é dirigido por `data.id`/`provider_charge_id` casando
 * com um Payment já existente (OrderPaymentService). O `action` da
 * notificação (`order.action_required`, `order.processed`, etc.) NUNCA é
 * ramificado diretamente — a doc pública não enumera todos os valores
 * possíveis; em vez disso, para qualquer evento que não seja
 * subscription_authorized_payment, a order é sempre reconsultada via
 * `GET /v1/orders/{id}` e o efeito decidido a partir do status real
 * retornado (getPayment()/reconcileOrderPayment). O ramo de ASSINATURA
 * (PegaTicket → tenant, Preapproval — roadmap Fase B item 1) é dirigido pelo
 * campo `type` da notificação (`subscription_authorized_payment` — cobrança
 * de ciclo aprovada/rejeitada), porque a cobrança recorrente do Preapproval
 * não passa por um Payment local pré-criado como no fluxo de pedido; o
 * SubscriptionService confirma/inicia período de graça a partir daí.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private OrderPaymentService $orderPaymentService,
        private SubscriptionService $subscriptionService,
        private InvoicePaymentService $invoicePaymentService,
    ) {
    }

    public function handle(Request $request, string $provider)
    {
        $externalId = $request->input('external_id')
            ?? $request->input('id')
            ?? $request->input('data.id');

        if ($provider !== 'mercadopago') {
            // Sem PSP real validando assinatura para este provider — mantém
            // o comportamento original (idempotência por external_id + 501),
            // não há assinatura possível de verificar.
            if ($externalId !== null && $externalId !== '') {
                $legacyType = (string) ($request->input('type') ?? $request->input('topic') ?? '');

                WebhookEvent::firstOrCreate(
                    ['provider' => $provider, 'type' => $legacyType, 'external_id' => (string) $externalId],
                    ['payload' => $this->sanitizedPayload($request), 'processed_at' => null]
                );
            }

            return APIResponse::error(
                __('messages.webhook.not_implemented'),
                501,
                'WEBHOOK_NOT_IMPLEMENTED'
            );
        }

        $mercadoPago = app(MercadoPagoPaymentProvider::class);

        // Validação de assinatura ANTES de qualquer escrita/idempotência
        // (security-standards.md, regra 8): um request não autenticado
        // nunca grava em webhook_events, senão um external_id forjado
        // poderia "reservar" a idempotência antes do evento legítimo
        // chegar, ou inflar a tabela com payload arbitrário do atacante.
        if (!$mercadoPago->validateWebhook($request)) {
            ApplicationLogger::warning('Webhook Mercado Pago rejeitado: assinatura inválida', [
                'provider' => $provider,
                'has_signature_header' => $request->hasHeader('x-signature'),
            ]);

            return APIResponse::error(
                __('messages.webhook.invalid_signature'),
                401,
                'WEBHOOK_INVALID_SIGNATURE'
            );
        }

        // Nunca processa notificação de sandbox (`live_mode=false`) em
        // produção — o Mercado Pago não separa ambiente por URL (é o
        // access_token TEST-/APP_USR- que decide), então um evento de teste
        // reentregue/simulado contra o endpoint de produção precisa ser
        // rejeitado explicitamente (regra 8 do agente de pagamentos).
        $liveMode = $request->input('live_mode');
        if (app()->environment('production') && $liveMode === false) {
            ApplicationLogger::warning('Webhook Mercado Pago rejeitado: evento de sandbox em produção', [
                'provider' => $provider,
            ]);

            return APIResponse::error(
                __('messages.webhook.invalid_signature'),
                401,
                'WEBHOOK_INVALID_SIGNATURE'
            );
        }

        // `type`/`topic` resolvido ANTES da idempotência: o Mercado Pago usa
        // `data.id` como identificador dentro de cada TIPO de recurso (order,
        // subscription_authorized_payment, chargeback, ...), sem garantia de
        // unicidade ENTRE tipos diferentes. `type` entra na chave de
        // idempotência (unique provider+type+external_id) para que um evento
        // nunca seja descartado por coincidir de id com um evento de OUTRO
        // tipo (achado real da auditoria de segurança — ver
        // architecture-decisions.md).
        $type = (string) ($request->input('type') ?? $request->input('topic') ?? '');

        // firstOrCreate garante idempotência mesmo sob corrida — o unique
        // (provider, type, external_id) impede duplicata. Só acontece depois
        // da assinatura validada.
        $event = null;
        if ($externalId !== null && $externalId !== '') {
            $event = WebhookEvent::firstOrCreate(
                ['provider' => $provider, 'type' => $type, 'external_id' => (string) $externalId],
                ['payload' => $this->sanitizedPayload($request), 'processed_at' => null]
            );
        }

        // Já processado (reentrega do PSP para o mesmo evento) — responde
        // sucesso sem reprocessar, idempotência real (não só o insert).
        if ($event !== null && $event->processed_at !== null) {
            return APIResponse::success(null, __('messages.webhook.received'));
        }

        // Qualquer chamada ao Mercado Pago abaixo (reconsulta obrigatória
        // antes de aplicar qualquer efeito local) pode falhar por motivo
        // transitório (rede, 5xx do MP, timeout). Isso NUNCA pode virar uma
        // exception crua estourando stack trace pro Mercado Pago: o evento
        // já foi persistido (idempotência acima) e continua com
        // `processed_at=null`, então a mesma notificação reentregue depois
        // reprocessa do zero com segurança — não há perda nem duplicidade.
        try {
            if ($type === 'subscription_authorized_payment') {
                $this->handleAuthorizedPayment($mercadoPago, (string) $request->input('data.id'));
            } elseif ($type === 'subscription_preapproval') {
                $this->handlePreapproval($mercadoPago, (string) $request->input('data.id'));
            } elseif (in_array($type, ['topic_chargebacks_wh', 'chargebacks'], true)) {
                $this->handleChargeback($mercadoPago, (string) $request->input('data.id'));
            } elseif (in_array($type, ['topic_claims_integration_wh'], true)) {
                $this->handleClaim($mercadoPago, (string) $request->input('data.id'));
            } elseif (in_array($type, ['delivery_cancellation', 'stop_delivery_op_wh'], true)) {
                $this->handleFraudAlert(
                    (string) ($request->input('provider_charge_id') ?? $request->input('charge_id') ?? $request->input('data.id'))
                );
            } else {
                // Cobre `type=order` (API de Orders, ex.: `action=order.action_
                // required`/`order.processed`) e, defensivamente, qualquer outro
                // valor não mapeado — `data.id` é o id da order
                // (ex. "ORD01JQ4S4KY8HWQ6NA5PXB65B3D3"). O `action` nunca decide
                // sozinho o efeito: reconcileOrderPayment sempre reconsulta a
                // order real via GET /v1/orders/{id} antes de confirmar.
                $orderId = $request->input('provider_charge_id')
                    ?? $request->input('charge_id')
                    ?? $request->input('data.id');

                if ($orderId !== null && $orderId !== '') {
                    $payment = Payment::where('provider', $provider)
                        ->where('provider_charge_id', (string) $orderId)
                        ->first();

                    if ($payment !== null && $payment->payable_type === Order::class) {
                        $this->reconcileOrderPayment($mercadoPago, $payment, (string) $orderId);
                    }
                }
            }
        } catch (PaymentProviderException $e) {
            ApplicationLogger::error('Falha ao reconsultar o Mercado Pago durante processamento de webhook', [
                'provider' => $provider,
                'type' => $type,
                'external_id' => $externalId,
                'webhook_event_uuid' => $event?->uuid,
                'exception_message' => $e->getMessage(),
            ]);

            // Não marca `processed_at` — o Mercado Pago reenvia a
            // notificação em caso de erro 5xx, e o próximo recebimento
            // reprocessa este mesmo evento (idempotência já garantida
            // acima). Resposta amigável, sem stack trace.
            return APIResponse::error(
                __('messages.webhook.processing_failed'),
                502,
                'WEBHOOK_PROCESSING_FAILED'
            );
        }

        if ($event !== null) {
            $event->processed_at = now();
            $event->save();
        }

        return APIResponse::success(null, __('messages.webhook.received'));
    }

    private function reconcileOrderPayment(
        MercadoPagoPaymentProvider $mercadoPago,
        Payment $payment,
        string $orderId
    ): void {
        $remote = $mercadoPago->getPayment($orderId);

        if (($remote['status'] ?? null) !== 'paid') {
            // Ainda pending/failed no MP — nada a conciliar agora, o
            // próximo webhook (approved) confirma.
            return;
        }

        $this->orderPaymentService->reconcileWebhookPayment($payment, (string) ($remote['amount'] ?? '0'));
    }

    /**
     * Notificação `type=subscription_authorized_payment` — uma cobrança de
     * ciclo do Preapproval foi processada (aprovada) ou rejeitada/cancelada
     * pelo Mercado Pago. Resolve a Subscription pelo `preapproval_id`
     * retornado na consulta ao authorized_payment (nunca confia só no
     * payload do webhook, sempre reconsulta o MP).
     */
    private function handleAuthorizedPayment(MercadoPagoPaymentProvider $mercadoPago, string $authorizedPaymentId): void
    {
        if ($authorizedPaymentId === '') {
            return;
        }

        $remote = $mercadoPago->getAuthorizedPayment($authorizedPaymentId);
        $preapprovalId = $remote['preapproval_id'] ?? null;

        if ($preapprovalId === null) {
            return;
        }

        $subscription = Subscription::where('preapproval_id', $preapprovalId)
            ->whereNull('deleted_at')
            ->first();

        if ($subscription === null) {
            return;
        }

        $status = $remote['status'] ?? null;

        if ($status === 'processed') {
            $this->subscriptionService->confirmCyclePayment($subscription, (string) ($remote['amount'] ?? '0'));
        } elseif (in_array($status, ['rejected', 'cancelled'], true)) {
            $this->subscriptionService->startGracePeriod($subscription);
        }
    }

    /**
     * Notificação `type=subscription_preapproval` — sincroniza mudança
     * estrutural do vínculo recorrente (ex.: cancelamento feito direto no
     * Mercado Pago), sempre reconsultando o recurso oficial antes de agir.
     */
    private function handlePreapproval(MercadoPagoPaymentProvider $mercadoPago, string $preapprovalId): void
    {
        if ($preapprovalId === '') {
            return;
        }

        $remote = $mercadoPago->getPreapproval($preapprovalId);

        $subscription = Subscription::where('preapproval_id', $preapprovalId)
            ->whereNull('deleted_at')
            ->first();

        if ($subscription === null) {
            return;
        }

        $status = (string) ($remote['status'] ?? '');

        if ($status === '') {
            return;
        }

        $this->subscriptionService->reconcilePreapprovalStatus($subscription, $status);
    }

    /**
     * Chargeback opcional do Mercado Pago. Reconsulta o caso oficial e
     * associa pelo transaction_id salvo em `payments.metadata.transaction_id`.
     * O efeito local é conservador: pagamento vira `divergent` e um Refund
     * append-only é aberto para revisão. Cobre tanto pagamento de PEDIDO
     * (`payable_type=Order`) quanto de FATURA de assinatura
     * (`payable_type=Invoice` — cobrança recorrente contestada pelo titular
     * do cartão), reaproveitando o mesmo Refund idempotente por
     * `provider_refund_id` nos dois casos.
     */
    private function handleChargeback(MercadoPagoPaymentProvider $mercadoPago, string $chargebackId): void
    {
        if ($chargebackId === '') {
            return;
        }

        $remote = $mercadoPago->getChargeback($chargebackId);
        $paymentIds = array_filter(array_map('strval', $remote['payments'] ?? []));

        if ($paymentIds === []) {
            return;
        }

        foreach ($paymentIds as $transactionId) {
            $payment = Payment::query()
                ->where('provider', 'mercadopago')
                ->where('metadata->transaction_id', $transactionId)
                ->first();

            $this->registerDisputeForPayment(
                $payment,
                'Chargeback sinalizado pelo Mercado Pago',
                (string) ($remote['id'] ?? $chargebackId),
                $remote['amount'] ?? null,
                $remote['resolution_date'] ?? null,
            );
        }
    }

    /**
     * Efeito de contestação (chargeback/claim) para um Payment já
     * resolvido, decidindo pelo `payable_type` se o efeito é o de PEDIDO
     * (OrderPaymentService) ou o de FATURA de assinatura
     * (InvoicePaymentService — soma o flag `disputed`/`dispute_deadline_at`
     * na fatura e audita a assinatura). Qualquer outro payable é ignorado
     * defensivamente (evento fica auditado em `webhook_events` mesmo assim).
     */
    private function registerDisputeForPayment(
        ?Payment $payment,
        string $reason,
        ?string $externalReference,
        string|int|float|null $amount,
        ?string $disputeDeadlineAt,
    ): void {
        if ($payment === null) {
            return;
        }

        if ($payment->payable_type === Order::class) {
            $this->orderPaymentService->registerExternalReview($payment, $reason, $externalReference, $amount);

            return;
        }

        if ($payment->payable_type === Invoice::class) {
            $invoice = Invoice::query()->find($payment->payable_id);

            if ($invoice === null) {
                return;
            }

            $this->invoicePaymentService->registerDisputedPayment(
                $payment,
                $invoice,
                $reason,
                $externalReference,
                $amount,
                $disputeDeadlineAt,
            );
        }
    }

    /**
     * Reclamação opcional do Mercado Pago. Só associa localmente quando o
     * recurso apontado é um pagamento conhecido; caso contrário, o evento
     * segue auditado em `webhook_events` para revisão manual. Cobre tanto
     * pagamento de PEDIDO quanto de FATURA de assinatura (mesmo critério de
     * handleChargeback).
     */
    private function handleClaim(MercadoPagoPaymentProvider $mercadoPago, string $claimId): void
    {
        if ($claimId === '') {
            return;
        }

        $remote = $mercadoPago->getClaim($claimId);

        if (($remote['resource'] ?? null) !== 'payment' || empty($remote['resource_id'])) {
            return;
        }

        $payment = Payment::query()
            ->where('provider', 'mercadopago')
            ->where('metadata->transaction_id', (string) $remote['resource_id'])
            ->first();

        $this->registerDisputeForPayment(
            $payment,
            'Reclamação aberta no Mercado Pago',
            (string) ($remote['id'] ?? $claimId),
            null,
            $remote['resolution_date'] ?? null,
        );
    }

    /**
     * Alerta antifraude/fraude pós-processamento. Sem endpoint oficial
     * reconsultável neste fluxo atual, então o efeito local é apenas
     * bloquear operacionalmente a cobrança para revisão.
     */
    private function handleFraudAlert(string $providerChargeId): void
    {
        if ($providerChargeId === '') {
            return;
        }

        $payment = Payment::query()
            ->where('provider', 'mercadopago')
            ->where('provider_charge_id', $providerChargeId)
            ->first();

        if ($payment === null || $payment->payable_type !== Order::class) {
            return;
        }

        $this->orderPaymentService->registerExternalReview(
            $payment,
            'Alerta antifraude do Mercado Pago',
            'fraud-alert:' . $providerChargeId,
        );
    }

    /**
     * Payload salvo em `webhook_events` nunca inclui cabeçalhos/segredos —
     * só o corpo da notificação (já é o que o PSP envia, sem access_token).
     *
     * @return array<string, mixed>
     */
    private function sanitizedPayload(Request $request): array
    {
        return $request->all();
    }
}
