<?php

namespace App\Support\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Refund;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Núcleo compartilhado de "sinalizar pagamento para revisão externa"
 * (chargeback/claim/alerta antifraude do Mercado Pago), usado tanto para
 * pagamentos de PEDIDO (SalePaymentService::registerExternalReview) quanto
 * de FATURA de assinatura (InvoicePaymentService::registerDisputedPayment) —
 * mesma tabela `payments`/`refunds`, mesmo estado conservador (`divergent` +
 * Refund append-only), sem duplicar a lógica de idempotência do Refund por
 * `provider_refund_id` entre os dois fluxos.
 */
class ExternalPaymentReviewRegistrar
{
    public function register(
        Payment $payment,
        string $reason,
        ?string $externalReference = null,
        string|int|float|null $amount = null
    ): Refund {
        return DB::transaction(function () use ($payment, $reason, $externalReference, $amount) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->loadMissing('payable');
            $tenantId = $payment->payable?->tenant_id;

            if (! in_array($payment->status, [PaymentStatus::Refunded, PaymentStatus::Failed], true)) {
                $payment->status = PaymentStatus::Divergent;
                $payment->save();
            }

            $existing = null;
            if ($externalReference !== null && $externalReference !== '') {
                $existing = Refund::query()
                    ->where('payment_id', $payment->id)
                    ->where('provider_refund_id', $externalReference)
                    ->first();
            }

            if ($existing !== null) {
                return $existing;
            }

            $refundAmount = $amount !== null
                ? Money::normalize($amount)
                : Money::normalize((string) $payment->amount);

            return Refund::create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment->id,
                'reason' => $reason,
                'amount' => $refundAmount,
                'type' => Money::equals((string) $payment->amount, $refundAmount) ? 'total' : 'partial',
                'requested_by' => Auth::id(),
                'protocol' => $this->generateProtocol(),
                'provider_refund_id' => $externalReference,
                'status' => 'requested',
            ]);
        });
    }

    private function generateProtocol(): string
    {
        return 'REF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
    }
}
