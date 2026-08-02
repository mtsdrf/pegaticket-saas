<?php

namespace App\Contracts\Payment;

use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use Illuminate\Http\Request;

/**
 * Contrato de provedor de pagamento (roadmap 1B). Abstrai o PSP real
 * (Pix/cartão) do domínio de cobrança. Implementação única nesta onda:
 * ManualPaymentProvider (no-op, sem PSP externo). Trocável por um adapter
 * real (Asaas/Efí/Mercado Pago) via binding no AppServiceProvider, sem
 * tocar em SubscriptionService/InvoiceService.
 */
interface PaymentProviderInterface
{
    /**
     * Cria uma cobrança Pix para a fatura via API de Orders do PSP
     * (`POST /v1/orders`, MercadoPagoPaymentProvider). Retorna metadados do
     * PSP (payment uuid, status, qrcode, etc.).
     *
     * @return array<string, mixed>
     */
    public function createPixCharge(Invoice $invoice): array;

    /**
     * Cria uma cobrança Pix para um PEDIDO (roadmap 2A — recebimento do
     * tenant, cliente final → tenant, Modelo A sem custódia) via API de
     * Orders do PSP. Simétrico a createPixCharge(Invoice), mas o payable é
     * um Sale. Retorna metadados do PSP (payment uuid, status, etc.).
     *
     * @return array<string, mixed>
     */
    public function createPixChargeForOrder(Sale $order): array;

    /**
     * Cria uma cobrança de cartão para a fatura via API de Orders do PSP.
     *
     * @param array<string, mixed> $cardToken
     * @return array<string, mixed>
     */
    public function createCardCharge(Invoice $invoice, array $cardToken): array;

    /**
     * Estorna uma order (total quando $amount é null, senão parcial —
     * `POST /v1/orders/{sale_id}/refund`).
     *
     * @return array<string, mixed>
     */
    public function refund(Payment $payment, string|int|float|null $amount = null): array;

    /**
     * Valida a assinatura/autenticidade de um webhook recebido do PSP.
     */
    public function validateWebhook(Request $request): bool;

    /**
     * Consulta o estado atual de uma order no PSP (`GET /v1/orders/{id}`).
     * Nome mantido por compatibilidade com os callers existentes (o recurso
     * consultado deixou de ser um payment legado e passou a ser uma order).
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $providerChargeId): array;

    /**
     * Cria a assinatura recorrente automática (Preapproval no Mercado
     * Pago) vinculada ao plan_price da Subscription — o PSP passa a cobrar
     * sozinho a cada ciclo a partir daí. Retorna ao menos
     * ['preapproval_id' => string, 'status' => string].
     *
     * `$operationPrefix` distingue a identidade lógica de idempotência
     * entre a criação inicial da assinatura ("preapproval_create") e a
     * troca de plano de uma assinatura já existente ("preapproval_change")
     * — usado pelo MercadoPagoPaymentProvider; adapters sem PSP real podem
     * ignorá-lo.
     *
     * `$cardTokenId` (decisão de produto 2026-07-24 — eliminar o
     * redirecionamento ao checkout do Mercado Pago): quando informado, o
     * Preapproval é criado E autorizado na mesma chamada síncrona
     * (`card_token_id` + `status: authorized`), sem `init_point`. Quando
     * `null`, mantém o comportamento legado (`status: pending`, sem
     * cartão) — não usado pelo fluxo normal a partir desta decisão, mas
     * preservado como fallback do adapter.
     *
     * @return array<string, mixed>
     */
    public function createPreapproval(Subscription $subscription, string $operationPrefix = 'preapproval_create', ?string $cardTokenId = null): array;

    /**
     * Cancela a assinatura recorrente automática no PSP (usada no
     * cancelamento imediato/fim-de-ciclo do lado PegaTicket, para não deixar
     * o PSP cobrando uma assinatura já encerrada aqui).
     *
     * @return array<string, mixed>
     */
    public function cancelPreapproval(Subscription $subscription): array;

    /**
     * Troca o cartão de uma assinatura recorrente automática já existente
     * (`PUT /preapproval/{id}` com o token do novo cartão, já tokenizado
     * pelo MP.js no frontend — nunca dado de cartão cru). Retorna ao menos
     * ['status' => string].
     *
     * @param array<string, mixed> $cardToken
     * @return array<string, mixed>
     */
    public function updatePreapprovalPaymentMethod(Subscription $subscription, array $cardToken): array;
}
