<?php

namespace App\Services\Payment;

use App\Models\Order\Order;
use App\Models\Payment\PaymentIdempotencyKey;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;
use App\Services\Logging\ApplicationLogger;
use App\Services\Order\OrderPaymentService;
use App\Services\Subscription\SubscriptionService;

/**
 * Núcleo ÚNICO de reconciliação ativa contra o Mercado Pago (2026-07-24,
 * painel administrativo de pendências de pagamento). Extraído de
 * `ReconcilePaymentIdempotencyCommand` e `ReconcileMercadoPagoOrderPaymentsCommand`
 * — os dois Commands agendados e o endpoint administrativo de reprocessamento
 * manual (`PaymentIssueController::reprocess`) chamam os MESMOS métodos
 * daqui, nunca duplicam a decisão de reconciliação (regra 12 do agente de
 * pagamentos: "nunca implemente diretamente em controllers/commands toda a
 * regra de negócio", e regra de não-duplicação do CLAUDE.md).
 */
class PaymentReconciliationService
{
    public function __construct(
        private MercadoPagoPaymentProvider $provider,
        private OrderPaymentService $orderPaymentService,
        private SubscriptionService $subscriptionService,
        private IdempotencyRepositoryInterface $idempotencyRepository,
    ) {
    }

    /**
     * Reconciliação ativa de uma cobrança Mercado Pago de PEDIDO ainda não
     * terminalizada localmente (pending/divergent/failed). Consulta o
     * status real no PSP antes de decidir qualquer efeito local — nunca
     * confia em estado local sozinho.
     */
    public function reconcileOrderPayment(Payment $payment): Payment
    {
        $remote = $this->provider->getPayment((string) $payment->provider_charge_id);

        return $this->orderPaymentService->reconcileRemotePayment(
            $payment,
            (string) ($remote['status'] ?? 'pending'),
            $remote['amount'] ?? (string) $payment->amount,
        );
    }

    /**
     * Reconciliação ativa do vínculo recorrente (`preapproval`) de uma
     * assinatura, a partir do status atual consultado no Mercado Pago.
     */
    public function reconcileSubscriptionPreapproval(Subscription $subscription): Subscription
    {
        $remote = $this->provider->getPreapproval((string) $subscription->preapproval_id);

        return $this->subscriptionService->reconcilePreapprovalStatus(
            $subscription,
            (string) ($remote['status'] ?? ''),
        );
    }

    /**
     * Fecha o loop do timeout ambíguo de idempotência: reconsulta o
     * Mercado Pago por `external_reference` para decidir com segurança o
     * que realmente aconteceu antes de qualquer nova tentativa ser
     * permitida. Nunca gera uma cobrança/assinatura nova sozinho.
     */
    public function reconcileIdempotencyRecord(PaymentIdempotencyKey $record): void
    {
        [$prefix, $identifier] = array_pad(explode(':', $record->operation, 2), 2, null);

        if ($identifier === null) {
            ApplicationLogger::error('Operação de idempotência com formato inesperado', [
                'idempotency_key_id' => $record->id,
                'operation' => $record->operation,
            ]);

            return;
        }

        match ($prefix) {
            'order_charge', 'invoice_charge' => $this->reconcileCharge($record, $prefix, $identifier),
            'preapproval_create', 'preapproval_change' => $this->reconcilePreapproval($record, $identifier),
            default => ApplicationLogger::error('Prefixo de operação de idempotência desconhecido', [
                'idempotency_key_id' => $record->id,
                'operation' => $record->operation,
            ]),
        };
    }

    private function reconcileCharge(PaymentIdempotencyKey $record, string $prefix, string $payableUuid): void
    {
        $existingPayment = Payment::where('idempotency_key', $record->idempotency_key)->first();

        if ($existingPayment !== null) {
            // O timeout foi só na resposta HTTP — o Payment local já foi
            // criado com sucesso (a chamada chegou a completar do nosso
            // lado). Só fecha a tentativa.
            $this->idempotencyRepository->markSucceeded($record, [
                'provider_charge_id' => $existingPayment->provider_charge_id,
                'resolved_via' => 'local_payment_found',
            ]);

            return;
        }

        $remote = $this->provider->searchOrderByExternalReference($payableUuid);

        if ($remote === null) {
            // Nada encontrado no Mercado Pago para essa referência — a
            // tentativa original nunca chegou a processar do lado deles.
            // Falha DECISIVA: libera uma tentativa nova imediata.
            $this->idempotencyRepository->markFailed($record);

            return;
        }

        $payable = $prefix === 'order_charge'
            ? Order::where('uuid', $payableUuid)->first()
            : Invoice::where('uuid', $payableUuid)->first();

        if ($payable === null) {
            ApplicationLogger::error('Recurso encontrado no Mercado Pago sem payable local correspondente', [
                'idempotency_key_id' => $record->id,
                'operation' => $record->operation,
                'provider_charge_id' => $remote['provider_charge_id'] ?? null,
            ]);

            $this->idempotencyRepository->markSucceeded($record, array_merge($remote, [
                'resolved_via' => 'remote_search_without_local_payable',
            ]));

            return;
        }

        // O Mercado Pago já criou a order — sincroniza o Payment local que
        // não foi salvo por causa do timeout (nunca duplica: a busca por
        // idempotency_key já garantiu que não existe Payment local ainda).
        Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => 'mercadopago',
            'provider_charge_id' => $remote['provider_charge_id'],
            'method' => 'pix',
            'amount' => $remote['amount'],
            'status' => $remote['status'],
            'idempotency_key' => $record->idempotency_key,
        ]);

        $this->idempotencyRepository->markSucceeded($record, array_merge($remote, [
            'resolved_via' => 'remote_search_created_local_payment',
        ]));
    }

    private function reconcilePreapproval(PaymentIdempotencyKey $record, string $subscriptionUuid): void
    {
        $subscription = Subscription::where('uuid', $subscriptionUuid)->first();

        $remote = $this->provider->searchPreapprovalByExternalReference($subscriptionUuid);

        if ($remote === null || empty($remote['preapproval_id'])) {
            // Nada encontrado no Mercado Pago — a tentativa original nunca
            // chegou a criar o preapproval do lado deles. Falha DECISIVA:
            // libera uma tentativa nova imediata.
            $this->idempotencyRepository->markFailed($record);

            return;
        }

        if ($subscription === null) {
            // Órfão real: a Subscription nunca foi persistida localmente
            // (a chamada ao PSP aconteceu antes da transação que a cria —
            // ver SubscriptionService::create()), mas o Mercado Pago
            // efetivamente criou o preapproval. Não recria a Subscription
            // sozinho aqui (decisão consciente); marca a tentativa como
            // resolvida e alerta a operação para revisão manual.
            ApplicationLogger::error('Preapproval órfão encontrado no Mercado Pago sem Subscription local — revisão manual necessária', [
                'idempotency_key_id' => $record->id,
                'operation' => $record->operation,
                'subscription_uuid' => $subscriptionUuid,
                'preapproval_id' => $remote['preapproval_id'],
                'remote_status' => $remote['status'] ?? null,
            ]);

            $this->idempotencyRepository->markSucceeded($record, array_merge($remote, [
                'resolved_via' => 'remote_search_orphan_requires_manual_review',
            ]));

            return;
        }

        if ((string) $subscription->preapproval_id !== (string) $remote['preapproval_id']) {
            $subscription->preapproval_id = $remote['preapproval_id'];
            $subscription->save();
        }

        $this->subscriptionService->reconcilePreapprovalStatus($subscription, (string) ($remote['status'] ?? ''));

        $this->idempotencyRepository->markSucceeded($record, array_merge($remote, [
            'resolved_via' => 'remote_search_synced_local_subscription',
        ]));
    }
}
