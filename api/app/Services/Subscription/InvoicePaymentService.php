<?php

namespace App\Services\Subscription;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Exceptions\Subscription\InvalidInvoicePaymentStateException;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Models\Subscription\SubscriptionEvent;
use App\Support\Payment\ExternalPaymentReviewRegistrar;
use Illuminate\Support\Facades\DB;

class InvoicePaymentService
{
    private const CHARGEABLE_STATUSES = ['open', 'overdue', 'failed'];

    /**
     * Faturas nesses status não devem ser sobrescritas por uma contestação
     * (já resolvidas em outro sentido — estorno concluído ou cancelamento).
     */
    private const NON_DISPUTABLE_STATUSES = ['refunded', 'canceled'];

    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private ExternalPaymentReviewRegistrar $externalReviewRegistrar,
    ) {
    }

    public function createOrReusePixCharge(Invoice $invoice): Payment
    {
        $this->assertBelongsToCurrentTenant($invoice);

        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $pendingCharge = $this->activePendingCharge($invoice);

            if ($pendingCharge !== null) {
                return $pendingCharge;
            }

            if (! in_array($invoice->status, self::CHARGEABLE_STATUSES, true)) {
                throw new InvalidInvoicePaymentStateException(__('messages.subscription.invoice_payment_not_available'));
            }

            $result = $this->paymentProvider->createPixCharge($invoice);

            return Payment::query()->where('uuid', $result['payment_uuid'])->firstOrFail();
        });
    }

    public function activePendingCharge(Invoice $invoice): ?Payment
    {
        return Payment::query()
            ->whereNull('deleted_at')
            ->where('payable_type', $invoice->getMorphClass())
            ->where('payable_id', $invoice->getKey())
            ->where('method', 'pix')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Contestação (chargeback/claim) do Mercado Pago para uma cobrança de
     * fatura de assinatura (`payable_type=Invoice`). Reaproveita o mesmo
     * núcleo de PEDIDO (ExternalPaymentReviewRegistrar — Payment vira
     * `divergent`, Refund append-only idempotente por `provider_refund_id`)
     * e soma o efeito específico de assinatura: a fatura fica `disputed`
     * (não sobrescreve fatura já `refunded`/`canceled`), grava o prazo de
     * contestação quando o Mercado Pago informar essa data no payload, e
     * audita a assinatura via SubscriptionEvent (mesmo padrão de
     * `cycle_payment_divergent` em SubscriptionService::confirmCyclePayment)
     * — visível no histórico interno mesmo sem alerta automático (roadmap
     * ainda não pede suspensão automática da assinatura em disputa).
     */
    public function registerDisputedPayment(
        Payment $payment,
        Invoice $invoice,
        string $reason,
        ?string $externalReference = null,
        string|int|float|null $amount = null,
        ?string $disputeDeadlineAt = null,
    ): Refund {
        $refund = $this->externalReviewRegistrar->register($payment, $reason, $externalReference, $amount);

        DB::transaction(function () use ($invoice, $disputeDeadlineAt) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($invoice->status, self::NON_DISPUTABLE_STATUSES, true)) {
                $invoice->status = 'disputed';
            }

            if ($disputeDeadlineAt !== null) {
                $invoice->dispute_deadline_at = $disputeDeadlineAt;
            }

            $invoice->save();

            SubscriptionEvent::create([
                'subscription_id' => $invoice->subscription_id,
                'type' => 'invoice_disputed',
                'payload' => [
                    'invoice_uuid' => $invoice->uuid,
                    'dispute_deadline_at' => $disputeDeadlineAt,
                ],
                'created_at' => now(),
            ]);
        });

        return $refund;
    }

    private function assertBelongsToCurrentTenant(Invoice $invoice): void
    {
        if ((int) $invoice->tenant_id !== (int) app('tenant_id')) {
            abort(404);
        }
    }
}
