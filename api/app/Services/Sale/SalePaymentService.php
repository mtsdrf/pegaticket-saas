<?php

namespace App\Services\Sale;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Events\Sale\SalePaid;
use App\Events\Sale\SalePaymentCharged;
use App\Events\Sale\SalePaymentRefundRequested;
use App\Exceptions\InvalidSaleStateException;
use App\Models\Sale\Sale;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
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
 * - duplicado: nunca há 2 cobranças Pix `pending` ativas pro mesmo pedido;
 * - cancelado-após-pago: vira um Refund `requested` (não apaga o pagamento);
 * - valor divergente: webhook que reporte valor diferente marca a cobrança
 *   como `divergent` em vez de confirmar (reconcileWebhookPayment).
 */
class SalePaymentService
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private ExternalPaymentReviewRegistrar $externalReviewRegistrar,
    ) {
    }

    /**
     * Cria uma cobrança Pix vinculada ao pedido. Rejeita se o pedido está
     * cancelado, já pago, ou já tem uma cobrança Pix ativa (`pending`).
     */
    public function createPixChargeForOrder(Sale $order): Payment
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

        $result = $this->paymentProvider->createPixChargeForOrder($order);

        $payment = Payment::where('uuid', $result['payment_uuid'])->firstOrFail();

        event(new SalePaymentCharged(
            saleUuid: $order->uuid,
            paymentUuid: $payment->uuid,
            method: $payment->method ?? 'pix',
            actorId: Auth::id()
        ));

        return $payment;
    }

    /**
     * Cobrança Pix ativa (status `pending`) do pedido, se houver. Usada tanto
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
     * Pagamento confirmado (status `paid`) do pedido, se houver — usado pelo
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
     * batendo confirma o pagamento e propaga para o pedido (is_paid).
     */
    public function reconcileWebhookPayment(Payment $payment, string|int|float $reportedAmount): Payment
    {
        return DB::transaction(function () use ($payment, $reportedAmount) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            if (!Money::equals((string) $payment->amount, $reportedAmount)) {
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
    public function reconcileRemotePayment(Payment $payment, string $remoteStatus, string|int|float $reportedAmount): Payment
    {
        return DB::transaction(function () use ($payment, $remoteStatus, $reportedAmount) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($remoteStatus === 'paid') {
                if (!Money::equals((string) $payment->amount, $reportedAmount)) {
                    $payment->status = 'divergent';
                    $payment->save();

                    return $payment;
                }

                if ($payment->status !== 'paid') {
                    $payment->status = 'paid';
                    $payment->paid_at = now();
                    $payment->save();
                    $this->markOrderPaid($payment);
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

            if ($remoteStatus === 'failed' && !in_array($payment->status, ['paid', 'refunded'], true)) {
                $payment->status = 'failed';
                $payment->save();
            }

            return $payment;
        });
    }

    /**
     * Estorno de um pedido pago que está sendo cancelado (roadmap 2A). Gera
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
            'reason' => 'Cancelamento de pedido pago',
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

                ApplicationLogger::error('Falha ao solicitar estorno no PSP para cancelamento de pedido', [
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
        return $this->externalReviewRegistrar->register($payment, $reason, $externalReference, $amount);
    }

    /**
     * Pagamento manual declarado pelo operador no fechamento do pedido.
     * Diferente de createPixChargeForOrder(): não há PSP nem webhook, então a
     * linha nasce `paid` imediatamente. Cria SÓ a linha em `payments`
     * (payable=Sale, provider='manual', status='paid'); NÃO mexe em
     * `sales.is_paid` — quem chama valida a soma de TODAS as formas contra o
     * total e decide marcar o pedido como pago só quando bate. Sem
     * transação/guard próprios: sempre chamado de dentro da transação do
     * fluxo operacional que fecha o pedido.
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
        if (!$payment->payable instanceof Sale) {
            return;
        }

        $order = $payment->payable;

        if ($order->is_paid) {
            return;
        }

        $order->is_paid = true;
        $order->paid_at = now();
        $order->paid_amount = $order->total_amount;
        $order->save();

        event(new SalePaid(
            saleUuid: $order->uuid,
            actorId: Auth::id()
        ));
    }

    private function generateProtocol(): string
    {
        return 'REF-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
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
