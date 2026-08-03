<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Adapter de pagamento manual (roadmap 1B) — não chama nenhum PSP externo.
 * createPixCharge/createCardCharge registram um Payment em status 'pending'
 * (a conciliação manual do admin marca como pago depois). validateWebhook
 * sempre retorna false: não há webhook real assinado nesta onda. refund
 * cria um registro em status 'requested'. Quando um PSP real for plugado,
 * basta um novo adapter implementando PaymentProviderInterface e trocar o
 * binding em AppServiceProvider (ponto de troca já documentado lá).
 */
class ManualPaymentProvider implements PaymentProviderInterface
{
    public function createPixCharge(Invoice $invoice): array
    {
        return $this->createPendingPayment($invoice, (string) $invoice->amount_net, 'pix');
    }

    public function createPixChargeForOrder(Sale $order): array
    {
        return $this->createPendingPayment($order, (string) $order->total_amount, 'pix');
    }

    public function createChargeForOrder(Sale $order, array $payload): array
    {
        $method = (string) ($payload['method'] ?? 'pix');

        return $this->createPendingPayment($order, (string) $order->total_amount, $method);
    }

    public function createCardCharge(Invoice $invoice, array $cardToken): array
    {
        return $this->createPendingPayment($invoice, (string) $invoice->amount_net, 'card');
    }

    public function refund(Payment $payment, string|int|float|null $amount = null): array
    {
        return [
            'status' => 'requested',
            'amount' => Money::normalize($amount ?? (string) $payment->amount),
        ];
    }

    public function validateWebhook(Request $request): bool
    {
        // Sem PSP real, nenhum webhook é considerado autêntico.
        return false;
    }

    public function getPayment(string $providerChargeId): array
    {
        return [
            'provider_charge_id' => $providerChargeId,
            'status' => 'pending',
        ];
    }

    public function getCheckoutConfig(): array
    {
        return [
            'provider' => 'manual',
            'available' => false,
            'environment' => null,
            'public_key' => null,
            'three_ds_session' => null,
            'three_ds_session_expires_at' => null,
        ];
    }

    public function createPreapproval(Subscription $subscription, string $operationPrefix = 'preapproval_create', ?string $cardTokenId = null): array
    {
        // Sem PSP real, não há cobrança recorrente automática — a
        // assinatura segue no fluxo manual (faturas geradas pelo cron,
        // conciliação manual do admin).
        return [
            'preapproval_id' => null,
            'status' => 'not_applicable',
        ];
    }

    public function cancelPreapproval(Subscription $subscription): array
    {
        return [
            'status' => 'not_applicable',
        ];
    }

    public function updatePreapprovalPaymentMethod(Subscription $subscription, array $cardToken): array
    {
        return [
            'status' => 'not_applicable',
        ];
    }

    /**
     * Registra um Payment pending polimórfico — o payable é uma Invoice
     * (cobrança de assinatura) OU um Sale (pagamento de pedido).
     *
     * @return array<string, mixed>
     */
    private function createPendingPayment(Model $payable, string|int|float $amount, string $method): array
    {
        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => 'manual',
            'provider_charge_id' => null,
            'method' => $method,
            'amount' => Money::normalize($amount),
            'status' => 'pending',
            'idempotency_key' => null,
        ]);

        return [
            'payment_uuid' => $payment->uuid,
            'status' => $payment->status,
            'method' => $method,
        ];
    }
}
