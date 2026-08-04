<?php

namespace App\Services\Sale;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Events\Sale\SaleApproved;
use App\Events\Sale\SalePaid;
use App\Events\Sale\SalePaymentCharged;
use App\Events\Sale\SalePaymentRefundRequested;
use App\Exceptions\InvalidSaleStateException;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Services\Finance\ExternalReviewFinancialAdjustmentService;
use App\Services\Logging\ApplicationLogger;
use App\Support\Money;
use App\Support\Payment\ExternalPaymentReviewRegistrar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pagamento de PEDIDO (roadmap 2A) — cliente final → tenant (Modelo A, sem
 * custódia). Distinto da cobrança de assinatura da Onda 1 (PegaTicket → tenant),
 * mas reaproveita a MESMA tabela polimórfica `payments` (payable=Sale) e a
 * MESMA interface PaymentProviderInterface (hoje resolvida pro
 * ManualPaymentProvider, sem PSP real).
 *
 * Casos especiais tratados como regra de negócio testável mesmo sem PSP real:
 * - duplicado: nunca há 2 cobranças Pix `pending` ativas pro mesma venda;
 * - cancelado-após-pago: vira um Refund `requested` (não apaga o pagamento);
 * - valor divergente: webhook que reporte valor diferente marca a cobrança
 *   como `divergent` em vez de confirmar (reconcileWebhookPayment).
 */
class SalePaymentService
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private ExternalPaymentReviewRegistrar $externalReviewRegistrar,
        private ExternalReviewFinancialAdjustmentService $externalReviewFinancialAdjustmentService,
    ) {}

    /**
     * Cria uma cobrança Pix vinculada aa venda. Rejeita se a venda está
     * cancelado, já pago, ou já tem uma cobrança Pix ativa (`pending`).
     */
    public function createPixChargeForOrder(Sale $order): Payment
    {
        return $this->createChargeForOrder($order, ['method' => 'pix']);
    }

    /**
     * Cria uma cobrança vinculada à venda usando o método informado
     * (`pix|credit_card|debit_card`). Mantém os mesmos guards de estado da
     * cobrança Pix para preservar idempotência e evitar cobranças ativas
     * duplicadas da mesma venda.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createChargeForOrder(Sale $order, array $payload): Payment
    {
        $this->assertBelongsToCurrentTenant($order);

        // Guard de estado validado sob lock, mas a transação FECHA antes
        // de chamar o PSP — segurar o lock de linha durante a chamada
        // HTTP não é necessário nem seguro (ver
        // MercadoPagoPaymentProvider::acquireIdempotency): a proteção real
        // contra corrida/duplo-clique passa a ser o lock de idempotência
        // persistido (`order_charge:{uuid}`), que sobrevive mesmo se a
        // chamada ao PSP der timeout — diferente do lock de linha, que
        // seria liberado por um rollback.
        DB::transaction(function () use ($order) {
            $locked = Sale::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->cancelled_at !== null) {
                throw new InvalidSaleStateException(__('messages.sale.already_cancelled'));
            }

            if ($locked->is_paid) {
                throw new InvalidSaleStateException(__('messages.sale.already_paid'));
            }

            if ($this->activePendingCharge($locked) !== null) {
                throw new InvalidSaleStateException(__('messages.sale.payment_charge_already_active'));
            }
        });

        $result = $this->paymentProvider->createChargeForOrder($order, $payload);

        $payment = Payment::where('uuid', $result['payment_uuid'])->firstOrFail();

        if ($payment->status !== 'pending') {
            $this->reconcileRemotePayment($payment, (string) $payment->status, (string) $payment->amount, $payment->metadata ?? []);
            $payment->refresh();
        }

        event(new SalePaymentCharged(
            saleUuid: $order->uuid,
            paymentUuid: $payment->uuid,
            method: $payment->method ?? 'pix',
            actorId: Auth::id()
        ));

        return $payment;
    }

    /**
     * Metadados necessários para inicializar o checkout do PSP no frontend
     * (chave pública, ambiente e sessão 3DS quando aplicável).
     *
     * @return array<string, mixed>
     */
    public function checkoutConfigForOrder(Sale $order): array
    {
        $this->assertBelongsToCurrentTenant($order);

        return $this->paymentProvider->getCheckoutConfig($order);
    }

    /**
     * Cobrança ativa (status `pending`) da venda, se houver. Usada tanto
     * pelo guard de duplicidade quanto pela decisão de reaproveitar em vez de
     * criar uma segunda.
     */
    public function activePendingCharge(Sale $order): ?Payment
    {
        return Payment::query()
            ->whereNull('deleted_at')
            ->where('payable_type', $order->getMorphClass())
            ->where('payable_id', $order->getKey())
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Pagamento confirmado (status `paid`) da venda, se houver — usado pelo
     * fluxo de cancelamento para decidir entre bloquear (pagamento manual
     * legado, sem registro em `payments`) e gerar estorno (pagamento Pix).
     */
    public function paidChargeForOrder(Sale $order): ?Payment
    {
        return Payment::query()
            ->whereNull('deleted_at')
            ->where('payable_type', $order->getMorphClass())
            ->where('payable_id', $order->getKey())
            ->where('status', 'paid')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Reconciliação de um evento de webhook do PSP (roadmap 2A — preparado,
     * ainda sem provedor real emitindo). Valida o valor reportado contra o
     * esperado ANTES de confirmar: divergência marca a cobrança como
     * `divergent` (nunca confirma sozinho, exige conferência humana). Valor
     * batendo confirma o pagamento e propaga para a venda (is_paid).
     */
    public function reconcileWebhookPayment(Payment $payment, string|int|float $reportedAmount): Payment
    {
        return DB::transaction(function () use ($payment, $reportedAmount) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            if (! Money::equals((string) $payment->amount, $reportedAmount)) {
                $payment->status = 'divergent';
                $payment->save();

                return $payment;
            }

            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            $this->markOrderPaid($payment);

            return $payment;
        });
    }

    /**
     * Reconciliação ativa a partir de um snapshot consultado no PSP
     * (polling/command), não necessariamente vindo de webhook.
     *
     * Regras:
     * - `paid`: confirma se o valor bate; divergência vira `divergent`
     * - `failed`: só derruba cobranças ainda não liquidadas
     * - `refunded`: espelha o estado remoto localmente
     * - `pending`: não muda nada
     */
    public function reconcileRemotePayment(Payment $payment, string $remoteStatus, string|int|float $reportedAmount, array $providerSnapshot = []): Payment
    {
        return DB::transaction(function () use ($payment, $remoteStatus, $reportedAmount, $providerSnapshot) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->mergeProviderSnapshot($payment, $providerSnapshot);

            if ($remoteStatus === 'paid') {
                if (! Money::equals((string) $payment->amount, $reportedAmount)) {
                    $payment->status = 'divergent';
                    $payment->save();

                    return $payment;
                }

                $shouldPersistPaid = $payment->status !== 'paid' || $payment->paid_at === null;
                $order = $payment->payable instanceof Sale ? $payment->payable : null;
                $shouldConfirmSale = $order instanceof Sale
                    && ($order->status === 'pending_approval' || ! $order->is_paid);

                if ($shouldPersistPaid) {
                    $payment->status = 'paid';
                    $payment->paid_at ??= now();
                    $payment->save();
                }

                if ($shouldConfirmSale) {
                    $this->markOrderPaid($payment);
                }

                return $payment;
            }

            if ($remoteStatus === 'authorized') {
                if (! in_array($payment->status, ['paid', 'refunded'], true)) {
                    $payment->status = 'authorized';
                    $payment->save();
                }

                return $payment;
            }

            if ($remoteStatus === 'in_analysis') {
                if (! in_array($payment->status, ['paid', 'refunded'], true)) {
                    $payment->status = 'in_analysis';
                    $payment->save();
                }

                return $payment;
            }

            if ($remoteStatus === 'canceled') {
                if (! in_array($payment->status, ['paid', 'refunded'], true)) {
                    $payment->status = 'canceled';
                    $payment->save();
                }

                return $payment;
            }

            if ($remoteStatus === 'refunded') {
                if ($payment->status !== 'refunded') {
                    $payment->status = 'refunded';
                    $payment->save();
                }

                return $payment;
            }

            if ($remoteStatus === 'failed' && ! in_array($payment->status, ['paid', 'refunded'], true)) {
                $payment->status = 'failed';
                $payment->save();
            }

            return $payment;
        });
    }

    /**
     * Estorno de uma venda pago que está sendo cancelado (roadmap 2A). Gera
     * um Refund `requested` amarrado ao pagamento confirmado — NÃO apaga o
     * pagamento. Retorna null quando não há pagamento Pix confirmado (nesse
     * caso o cancelamento segue seu guard normal em SaleService).
     */
    public function createRefundForPaidCancellation(Sale $order): ?Refund
    {
        $payment = $this->paidChargeForOrder($order);

        if ($payment === null) {
            return null;
        }

        $refund = Refund::create([
            'tenant_id' => $order->tenant_id,
            'payment_id' => $payment->id,
            'reason' => 'Cancelamento de venda pago',
            'amount' => Money::normalize((string) $payment->amount),
            'type' => 'total',
            'requested_by' => Auth::id(),
            'protocol' => $this->generateProtocol(),
            'status' => 'requested',
        ]);

        if ($payment->provider !== 'manual' && $payment->provider_charge_id !== null) {
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

                ApplicationLogger::error('Falha ao solicitar estorno no PSP para cancelamento de venda', [
                    'sale_uuid' => $order->uuid,
                    'payment_uuid' => $payment->uuid,
                ]);
            }
        }

        event(new SalePaymentRefundRequested(
            saleUuid: $order->uuid,
            refundProtocol: $refund->protocol,
            actorId: Auth::id()
        ));

        return $refund;
    }

    /**
     * Chargeback/fraude não é tratado como "pagamento confirmado" nem como
     * estorno plenamente liquidado sem revisão. O estado seguro é
     * `divergent`, com Refund append-only para rastreabilidade.
     */
    public function registerExternalReview(
        Payment $payment,
        string $reason,
        ?string $externalReference = null,
        string|int|float|null $amount = null
    ): Refund {
        $refund = $this->externalReviewRegistrar->register($payment, $reason, $externalReference, $amount);

        if ($payment->payable_type === Sale::class) {
            $this->externalReviewFinancialAdjustmentService->handleRefundReview($refund);
        }

        return $refund;
    }

    /**
     * Pagamento manual declarado pelo operador no fechamento da venda.
     * Diferente de createPixChargeForOrder(): não há PSP nem webhook, então a
     * linha nasce `paid` imediatamente. Cria SÓ a linha em `payments`
     * (payable=Sale, provider='manual', status='paid'); NÃO mexe em
     * `sales.is_paid` — quem chama valida a soma de TODAS as formas contra o
     * total e decide marcar a venda como pago só quando bate. Sem
     * transação/guard próprios: sempre chamado de dentro da transação do
     * fluxo operacional que fecha a venda.
     */
    public function registerManualPayment(Sale $order, string $method, float $amount): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->getKey(),
            'provider' => 'manual',
            'method' => $method,
            'amount' => number_format($amount, 2, '.', ''),
            'status' => 'paid',
            'paid_at' => now(),
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function markOrderPaid(Payment $payment): void
    {
        if (! $payment->payable instanceof Sale) {
            return;
        }

        $order = $payment->payable;
        $shouldAutoApprove = $order->origin === 'storefront' && $order->status === 'pending_approval';

        if ($order->is_paid && ! $shouldAutoApprove) {
            return;
        }

        if ($shouldAutoApprove) {
            $order->status = 'confirmed';
        }

        $order->is_paid = true;
        $order->paid_at ??= now();
        $order->paid_amount = $order->total_amount;
        $order->save();

        if ($shouldAutoApprove) {
            event(new SaleApproved(
                saleId: $order->id,
                saleUuid: $order->uuid,
                fromStage: 'approval',
                toStage: 'confirmed',
                actorId: Auth::id()
            ));
        }

        event(new SalePaid(
            saleUuid: $order->uuid,
            actorId: Auth::id()
        ));
    }

    private function generateProtocol(): string
    {
        return 'REF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
    }

    private function mergeProviderSnapshot(Payment $payment, array $providerSnapshot): void
    {
        if ($providerSnapshot === []) {
            return;
        }

        $payment->metadata = array_merge($payment->metadata ?? [], $providerSnapshot);
        $payment->save();
    }

    /**
     * Route-model-binding resolve só por uuid, sem escopo de tenant — mesmo
     * cuidado de IDOR aplicado em SaleService.
     */
    private function assertBelongsToCurrentTenant(Sale $order): void
    {
        if ((int) $order->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
