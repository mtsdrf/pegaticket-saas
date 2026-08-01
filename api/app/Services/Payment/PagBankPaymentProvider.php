<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PagBankChargeRequestDTO;
use App\DTOs\Payment\PagBankChargeResponseDTO;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Adapter PagBank para o rail comprador -> tenant (venda de ingresso,
 * StorefrontCheckoutService / SalePaymentService). NÃO é usado pela
 * assinatura Clube->PegaTicket (essa continua exclusivamente em
 * MercadoPagoPaymentProvider) — ver AppServiceProvider, binding contextual
 * de PaymentProviderInterface para SalePaymentService.
 *
 * ==========================================================================
 * STATUS DESTA CLASSE: STRUCTURAL STUB, NÃO INTEGRAÇÃO REAL.
 * ==========================================================================
 * Não há credenciais PagBank fornecidas nesta sessão, então nenhuma chamada
 * HTTP real à API do PagBank foi implementada — os métodos abaixo têm a
 * assinatura, o fluxo de validação, o tratamento de erro e o ponto de
 * configuração já prontos (mesmo formato de retorno de
 * MercadoPagoPaymentProvider/ManualPaymentProvider), mas o corpo da
 * chamada HTTP está marcado explicitamente com `// TODO PAGBANK REAL:` —
 * é o único trecho que falta quando as credenciais reais existirem. Nenhum
 * endpoint, payload ou nome de campo da API do PagBank foi inventado; os
 * DTOs em App\DTOs\Payment\PagBank* são placeholders internos, não uma
 * cópia da API real.
 *
 * Enquanto ficar neste estado, createPixChargeForOrder() se comporta como
 * o ManualPaymentProvider (registra um Payment `pending`, sem cobrar nada
 * de verdade) — o pedido segue exigindo conciliação manual até a
 * integração real ser feita, exatamente como já acontece hoje com
 * PAYMENT_PROVIDER=manual.
 */
class PagBankPaymentProvider implements PaymentProviderInterface
{
    private const BASE_URL_SANDBOX = 'https://sandbox.api.pagseguro.com';
    private const BASE_URL_PRODUCTION = 'https://api.pagseguro.com';

    public function createPixCharge(Invoice $invoice): array
    {
        // Rail de assinatura (fatura PegaTicket->tenant) não usa PagBank
        // nesta onda — só o rail de venda de ingresso (Sale) usa este
        // provider, ver docblock da classe. Mantido implementado (em vez
        // de lançar) por completude da interface e para não impedir um
        // uso futuro de fatura avulsa via PagBank.
        return $this->registerPendingCharge($invoice, (string) $invoice->amount_net, 'pix');
    }

    public function createPixChargeForOrder(Sale $order): array
    {
        $request = PagBankChargeRequestDTO::fromArray([
            'reference_id' => (string) $order->uuid,
            'amount' => Money::normalize((string) $order->total_amount),
            'method' => 'pix',
            'payer' => $this->resolveOrderPayer($order),
            'payment_method' => ['type' => 'pix'],
        ]);

        return $this->chargeAndPersist($order, $request);
    }

    public function createCardCharge(Invoice $invoice, array $cardToken): array
    {
        // Cartão de fatura de assinatura não é usado por este adapter
        // (rail comprador->tenant só cobra Pix de pedido nesta onda,
        // espelhando o escopo atual de SalePaymentService). Implementado
        // de forma estrutural só para satisfazer a interface.
        return $this->registerPendingCharge($invoice, (string) $invoice->amount_net, 'card');
    }

    public function refund(Payment $payment, string|int|float|null $amount = null): array
    {
        $refundAmount = $amount !== null ? Money::normalize($amount) : Money::normalize((string) $payment->amount);

        // TODO PAGBANK REAL: chamar o endpoint de estorno da API de Pedidos
        // do PagBank (charge/order id em $payment->provider_charge_id) e
        // propagar o status real da resposta. Sem credenciais, apenas
        // registra a intenção — mesmo comportamento defensivo do
        // ManualPaymentProvider::refund().
        return [
            'status' => 'requested',
            'amount' => $refundAmount,
        ];
    }

    public function validateWebhook(Request $request): bool
    {
        $secret = (string) config('services.pagbank.webhook_secret', '');

        // TODO PAGBANK REAL: sem o formato oficial de assinatura de webhook
        // do PagBank confirmado nesta sessão, não há validação segura
        // possível — recusar tudo é a única postura correta até isso ser
        // implementado (nunca aceitar um webhook sem validar assinatura).
        if ($secret === '') {
            return false;
        }

        return false;
    }

    public function getPayment(string $providerChargeId): array
    {
        // TODO PAGBANK REAL: GET no recurso de pedido/cobrança do PagBank
        // por $providerChargeId e mapear o status real. Sem credenciais,
        // retorna um snapshot 'pending' — o caller (reconciliação) trata
        // isso como "ainda sem confirmação", nunca como sucesso.
        return [
            'provider_charge_id' => $providerChargeId,
            'status' => 'pending',
        ];
    }

    public function createPreapproval(Subscription $subscription, string $operationPrefix = 'preapproval_create', ?string $cardTokenId = null): array
    {
        // Assinatura recorrente (Clube->PegaTicket) não é um caso de uso
        // deste adapter — PagBank aqui só existe para o rail
        // comprador->tenant, ver docblock da classe. 'not_applicable' segue
        // o mesmo contrato do ManualPaymentProvider para métodos fora do
        // escopo do adapter.
        return ['preapproval_id' => null, 'status' => 'not_applicable'];
    }

    public function cancelPreapproval(Subscription $subscription): array
    {
        return ['status' => 'not_applicable'];
    }

    public function updatePreapprovalPaymentMethod(Subscription $subscription, array $cardToken): array
    {
        return ['status' => 'not_applicable'];
    }

    /**
     * Monta a cobrança, chama o PagBank (stub) e persiste o Payment local
     * — mesmo formato de retorno de MercadoPagoPaymentProvider::createOrder()
     * ({payment_uuid, status, method}), para que SalePaymentService não
     * precise saber qual adapter está por trás da interface.
     *
     * @return array<string, mixed>
     */
    private function chargeAndPersist(Model $payable, PagBankChargeRequestDTO $request): array
    {
        $response = $this->callPagBank($request);

        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => 'pagbank',
            'provider_charge_id' => $response->providerChargeId !== '' ? $response->providerChargeId : null,
            'method' => $response->method,
            'amount' => $request->amount,
            'status' => $response->status,
            'idempotency_key' => null,
            'metadata' => $response->metadata,
        ]);

        return [
            'payment_uuid' => $payment->uuid,
            'status' => $payment->status,
            'method' => $response->method,
        ];
    }

    /**
     * Ponto único de chamada HTTP ao PagBank. Hoje não chama rede nenhuma
     * — retorna uma resposta `pending` sintética, o mesmo estado seguro que
     * o ManualPaymentProvider persiste hoje (pedido aguarda conciliação
     * manual). Quando a integração real for feita, o corpo marcado abaixo
     * é o único trecho a substituir; a assinatura do método e o contrato de
     * retorno (PagBankChargeResponseDTO) já ficam prontos para receber o
     * mapeamento da resposta real.
     */
    private function callPagBank(PagBankChargeRequestDTO $request): PagBankChargeResponseDTO
    {
        $token = (string) config('services.pagbank.token', '');

        if ($token === '') {
            // Sem credencial configurada, a cobrança nasce pending local
            // (mesmo comportamento seguro do ManualPaymentProvider) em vez
            // de falhar — permite ligar o provider via config antes das
            // credenciais existirem, sem quebrar o checkout.
            return PagBankChargeResponseDTO::fromArray([
                'id' => '',
                'status' => 'pending',
                'method' => $request->method,
                'metadata' => ['note' => 'pagbank_stub_no_credentials'],
            ]);
        }

        // TODO PAGBANK REAL: substituir o bloco abaixo pela chamada real,
        // por exemplo algo no formato:
        //   $response = $this->client()->post('/orders', $request->toArray());
        //   $this->assertSuccessful($response, 'createOrder');
        //   return PagBankChargeResponseDTO::fromArray($response->json());
        // Nenhum endpoint/payload real foi confirmado contra a documentação
        // oficial do PagBank nesta sessão — não inventar aqui.
        return PagBankChargeResponseDTO::fromArray([
            'id' => '',
            'status' => 'pending',
            'method' => $request->method,
            'metadata' => ['note' => 'pagbank_stub_credentials_present_but_http_call_not_implemented'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOrderPayer(Sale $order): array
    {
        $order->loadMissing('finalCustomer');

        $customer = $order->finalCustomer;

        if ($customer === null) {
            return [];
        }

        return array_filter([
            'email' => $customer->email ?: null,
            'name' => $customer->name ?: null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * Comportamento equivalente ao ManualPaymentProvider — registra um
     * Payment `pending` sem chamar nenhum PSP. Usado pelos caminhos deste
     * adapter que estão fora do escopo do rail comprador->tenant
     * (createPixCharge/createCardCharge de Invoice).
     *
     * @return array<string, mixed>
     */
    private function registerPendingCharge(Model $payable, string|int|float $amount, string $method): array
    {
        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => 'pagbank',
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

    /**
     * Ponto de configuração pronto para quando a chamada real existir —
     * ainda não usado (ver TODO PAGBANK REAL em callPagBank()).
     */
    private function client(): PendingRequest
    {
        $environment = (string) config('services.pagbank.environment', 'sandbox');
        $baseUrl = $environment === 'production' ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
        $token = (string) config('services.pagbank.token', '');

        return Http::withToken($token)
            ->baseUrl($baseUrl)
            ->timeout(15);
    }

    /**
     * Mantido por simetria com MercadoPagoPaymentProvider — ainda sem uso
     * real (ver TODO PAGBANK REAL). Lança PaymentProviderException com um
     * código curto interno, mesmo padrão do adapter Mercado Pago.
     */
    private function assertSuccessful(\Illuminate\Http\Client\Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        throw new PaymentProviderException('pagbank.request_failed');
    }
}
