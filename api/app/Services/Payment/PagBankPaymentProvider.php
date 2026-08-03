<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PagBankChargeRequestDTO;
use App\DTOs\Payment\PagBankChargeResponseDTO;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Adapter PagBank para o rail comprador -> tenant (venda de ingresso,
 * StorefrontCheckoutService / SalePaymentService). NÃO é usado pela
 * assinatura Clube->PegaTicket (essa continua exclusivamente em
 * MercadoPagoPaymentProvider) — ver AppServiceProvider, binding contextual
 * de PaymentProviderInterface para SalePaymentService.
 *
 * ==========================================================================
 * INTEGRAÇÃO REAL — API de Vendas (Orders) do PagBank, confirmada contra
 * a documentação oficial em developer.pagbank.com.br (2026-08-02):
 * - Criar venda com QR Code Pix: POST /orders (developer.pagbank.com.br/
 *   reference/criar-venda-venda-com-qr-code).
 * - Consultar venda: GET /orders/{id} (developer.pagbank.com.br/docs/
 *   vendas-e-pagamentos-order).
 * - Cancelar/estornar charge: POST /charges/{charge_id}/cancel
 *   (developer.pagbank.com.br/reference/consultar-pagamento e discussões
 *   oficiais — PagBank decide sozinho parcial/total pela presença de
 *   `amount`).
 * - Autenticidade do webhook: header `x-authenticity-token` = SHA-256(
 *   token + '-' + payload_bruto) (developer.pagbank.com.br/reference/
 *   confirmar-autenticidade-da-notificacao). O payload do webhook é o
 *   próprio Order (mesmo shape do GET /orders/{id}) — por isso o efeito
 *   local NUNCA confia no corpo da notificação: só usa `id` para localizar
 *   o Payment e sempre reconsulta via getPayment() antes de aplicar
 *   qualquer mudança de estado (mesma postura de MercadoPagoPaymentProvider).
 *
 * Sem token configurado (`PAGBANK_TOKEN_SANDBOX`/`PAGBANK_TOKEN_PROD`), o
 * provider continua se comportando como o ManualPaymentProvider (Payment
 * `pending` local, sem chamada de rede) — permite ligar
 * SALE_PAYMENT_PROVIDER=pagbank antes das credenciais existirem sem
 * quebrar o checkout.
 */
class PagBankPaymentProvider implements PaymentProviderInterface
{
    private const BASE_URL_SANDBOX = 'https://sandbox.api.pagseguro.com';

    private const BASE_URL_PRODUCTION = 'https://api.pagseguro.com';

    private const SDK_BASE_URL_SANDBOX = 'https://sandbox.sdk.pagseguro.com';

    private const SDK_BASE_URL_PRODUCTION = 'https://sdk.pagseguro.com';

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
        return $this->createChargeForOrder($order, ['method' => 'pix']);
    }

    public function createChargeForOrder(Sale $order, array $payload): array
    {
        $method = (string) ($payload['method'] ?? 'pix');
        $request = PagBankChargeRequestDTO::fromArray([
            'reference_id' => (string) $order->uuid,
            'amount' => Money::normalize((string) $order->total_amount),
            'method' => $method,
            'payer' => $this->resolveOrderPayer($order, $payload),
            'payment_method' => $this->resolvePaymentMethodPayload($method, $payload),
        ]);

        return $this->chargeAndPersist($order, $request);
    }

    public function createCardCharge(Invoice $invoice, array $cardToken): array
    {
        // Cartão de fatura de assinatura não é usado por este adapter
        // (rail comprador->tenant só cobra Pix de venda nesta onda,
        // espelhando o escopo atual de SalePaymentService). Implementado
        // de forma estrutural só para satisfazer a interface.
        return $this->registerPendingCharge($invoice, (string) $invoice->amount_net, 'card');
    }

    public function refund(Payment $payment, string|int|float|null $amount = null): array
    {
        $refundAmount = $amount !== null ? Money::normalize($amount) : Money::normalize((string) $payment->amount);
        $token = (string) config('services.pagbank.token', '');
        $orderId = (string) $payment->provider_charge_id;

        if ($token === '' || $orderId === '') {
            // Sem credencial ou sem id de venda remoto (cobrança nasceu
            // pending local, nunca chegou a existir no PagBank) — só
            // registra a intenção, mesma postura do ManualPaymentProvider.
            return ['status' => 'requested', 'amount' => $refundAmount];
        }

        try {
            $chargeId = $this->resolveChargeId($orderId);

            if ($chargeId === null) {
                // Venda ainda não tem charge paga no PagBank (nunca foi
                // pago) — nada a estornar de fato.
                return ['status' => 'requested', 'amount' => $refundAmount];
            }

            $body = array_filter([
                'amount' => ['value' => Money::toMinor($refundAmount)],
            ]);

            $response = $this->client()->post("/charges/{$chargeId}/cancel", $body);
            $this->assertSuccessful($response, 'cancelCharge');

            $status = (string) $response->json('status', '');

            return [
                'status' => $status === 'CANCELED' ? 'refunded' : 'requested',
                'amount' => $refundAmount,
            ];
        } catch (PaymentProviderException $e) {
            return ['status' => 'requested', 'amount' => $refundAmount, 'error' => $e->getMessage()];
        }
    }

    public function validateWebhook(Request $request): bool
    {
        $token = (string) config('services.pagbank.token', '');
        $header = (string) $request->header('x-authenticity-token', '');

        if ($token === '' || $header === '') {
            return false;
        }

        $rawPayload = $request->getContent();
        $expected = hash('sha256', $token.'-'.$rawPayload);

        return hash_equals($expected, $header);
    }

    public function getPayment(string $providerChargeId): array
    {
        $token = (string) config('services.pagbank.token', '');

        if ($token === '' || $providerChargeId === '') {
            return ['provider_charge_id' => $providerChargeId, 'status' => 'pending'];
        }

        try {
            $response = $this->client()->get("/orders/{$providerChargeId}");
            $this->assertSuccessful($response, 'getOrder');

            $charges = (array) $response->json('charges', []);
            $firstCharge = $charges[0] ?? null;

            if ($firstCharge === null) {
                // Venda criado mas ainda sem cobrança paga (Pix aguardando
                // o comprador escanear o QR Code).
                return ['provider_charge_id' => $providerChargeId, 'status' => 'pending'];
            }

            return [
                'provider_charge_id' => $providerChargeId,
                'status' => $this->mapStatus((string) ($firstCharge['status'] ?? '')),
                'amount' => isset($firstCharge['amount']['value'])
                    ? Money::normalize(((int) $firstCharge['amount']['value']) / 100)
                    : null,
                'raw_status' => $firstCharge['status'] ?? null,
            ];
        } catch (PaymentProviderException) {
            return ['provider_charge_id' => $providerChargeId, 'status' => 'pending'];
        }
    }

    public function getCheckoutConfig(): array
    {
        $token = (string) config('services.pagbank.token', '');
        $environment = (string) config('services.pagbank.environment', 'sandbox');

        if ($token === '') {
            return [
                'provider' => 'pagbank',
                'available' => false,
                'environment' => $environment === 'production' ? 'PROD' : 'SANDBOX',
                'public_key' => null,
                'three_ds_session' => null,
                'three_ds_session_expires_at' => null,
                'sdk_script_url' => 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
            ];
        }

        $publicKeyResponse = $this->client()->post('/public-keys', ['type' => 'card']);
        $this->assertSuccessful($publicKeyResponse, 'createPublicKey');

        $sessionResponse = $this->sdkClient()->post('/checkout-sdk/sessions');
        $this->assertSuccessful($sessionResponse, 'createThreeDsSession');

        return [
            'provider' => 'pagbank',
            'available' => true,
            'environment' => $environment === 'production' ? 'PROD' : 'SANDBOX',
            'public_key' => (string) ($publicKeyResponse->json('public_key') ?? $publicKeyResponse->json('publicKey') ?? ''),
            'three_ds_session' => (string) ($sessionResponse->json('session') ?? ''),
            'three_ds_session_expires_at' => now()->addMinutes(30)->toIso8601String(),
            'sdk_script_url' => 'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
        ];
    }

    /**
     * Vocabulário interno usado por SalePaymentService/reconciliação (mesmo
     * de MercadoPagoPaymentProvider::mapStatus) — mapeado a partir dos
     * status documentados de charge (PAID/AUTHORIZED/IN_ANALYSIS/DECLINED/
     * CANCELED/WAITING, developer.pagbank.com.br/reference/objeto-order).
     */
    private function mapStatus(string $pagBankStatus): string
    {
        return match ($pagBankStatus) {
            'PAID', 'AUTHORIZED' => 'paid',
            'DECLINED' => 'failed',
            'CANCELED' => 'refunded',
            default => 'pending', // inclui WAITING, IN_ANALYSIS, ''
        };
    }

    /**
     * Resolve o charge_id necessário para POST /charges/{id}/cancel a
     * partir do order_id salvo em provider_charge_id — o cancelamento é
     * por charge, não por order (developer.pagbank.com.br/reference/
     * consultar-pagamento). Retorna null quando a venda não tem nenhuma
     * charge paga ainda.
     */
    private function resolveChargeId(string $orderId): ?string
    {
        $response = $this->client()->get("/orders/{$orderId}");
        $this->assertSuccessful($response, 'getOrder');

        $charges = (array) $response->json('charges', []);
        $paidCharge = null;

        foreach ($charges as $charge) {
            if (($charge['status'] ?? null) === 'PAID') {
                $paidCharge = $charge;
                break;
            }
        }

        $chargeId = $paidCharge['id'] ?? null;

        return $chargeId !== null ? (string) $chargeId : null;
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
     * Ponto único de chamada HTTP ao PagBank — POST /orders tanto para Pix
     * quanto para cartão, usando a API de Orders oficial.
     */
    private function callPagBank(PagBankChargeRequestDTO $request): PagBankChargeResponseDTO
    {
        $token = (string) config('services.pagbank.token', '');

        if ($token === '') {
            return PagBankChargeResponseDTO::fromArray([
                'id' => '',
                'status' => 'pending',
                'method' => $request->method,
                'metadata' => ['note' => 'pagbank_stub_no_credentials'],
            ]);
        }

        $amountCents = Money::toMinor($request->amount);

        $body = array_filter([
            'reference_id' => $request->referenceId,
            'customer' => array_filter([
                'name' => $request->payer['name'] ?? null,
                'email' => $request->payer['email'] ?? null,
                'tax_id' => $request->payer['tax_id'] ?? null,
                'phones' => !empty($request->payer['phone'])
                    ? [[
                        'country' => '55',
                        'area' => substr($this->normalizeDocument((string) $request->payer['phone']), 0, 2),
                        'number' => substr($this->normalizeDocument((string) $request->payer['phone']), 2),
                        'type' => 'MOBILE',
                    ]]
                    : null,
            ]) ?: null,
            'items' => [[
                'name' => 'Compra '.$request->referenceId,
                'quantity' => 1,
                'unit_amount' => $amountCents,
            ]],
            'qr_codes' => $request->method === 'pix'
                ? [[
                    'amount' => ['value' => $amountCents],
                ]]
                : null,
            'charges' => $request->method !== 'pix'
                ? [[
                    'reference_id' => 'charge-'.Str::lower(Str::random(12)),
                    'description' => 'Venda '.$request->referenceId,
                    'amount' => [
                        'value' => $amountCents,
                        'currency' => 'BRL',
                    ],
                    'payment_method' => $request->paymentMethod,
                ]]
                : null,
            'notification_urls' => [url('/api/v1/webhooks/payments/pagbank')],
        ]);

        try {
            $response = $this->client()->post('/orders', $body);
            $this->assertSuccessful($response, 'createOrder');

            $firstCharge = $response->json('charges.0', []);
            $qrCode = $response->json('qr_codes.0', []);
            $chargeStatus = (string) ($firstCharge['status'] ?? '');

            return PagBankChargeResponseDTO::fromArray([
                'id' => $response->json('id', ''),
                'status' => $request->method === 'pix' ? 'pending' : $this->mapStatus($chargeStatus),
                'method' => $request->method,
                'metadata' => [
                    'qr_code_text' => $qrCode['text'] ?? null,
                    'qr_code_id' => $qrCode['id'] ?? null,
                    'expiration_date' => $qrCode['expiration_date'] ?? null,
                    'raw_status' => $chargeStatus !== '' ? $chargeStatus : null,
                    'payment_response' => $firstCharge['payment_response'] ?? null,
                    'card' => $firstCharge['payment_method']['card'] ?? null,
                ],
            ]);
        } catch (PaymentProviderException) {
            return PagBankChargeResponseDTO::fromArray([
                'id' => '',
                'status' => 'failed',
                'method' => $request->method,
                'metadata' => ['note' => 'pagbank_order_creation_failed'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOrderPayer(Sale $order, array $payload = []): array
    {
        $order->loadMissing(['finalCustomer', 'finalCustomerLink']);

        $customer = $order->finalCustomer;
        $customerLink = $order->finalCustomerLink;

        if ($customerLink === null && $order->final_customer_id !== null) {
            $customerLink = FinalCustomerTenantLink::query()
                ->where('final_customer_id', $order->final_customer_id)
                ->where('tenant_id', $order->tenant_id)
                ->first();
        }

        if ($customer === null) {
            return [];
        }

        $taxId = $this->normalizeDocument(
            (string) ($payload['payer_tax_id'] ?? $customerLink?->cpf_cnpj ?? $payload['card']['holder_tax_id'] ?? '')
        );

        if ($taxId === '') {
            throw new PaymentProviderException('pagbank.missing_payer_tax_id');
        }

        return array_filter([
            'email' => ($payload['payer_email'] ?? null) ?: ($customer->email ?: null),
            'name' => ($payload['payer_name'] ?? null)
                ?: (trim(($customer->name ?? '').' '.($customer->last_name ?? '')) ?: ($customer->name ?: null)),
            'phone' => ($payload['payer_phone'] ?? null) ?: ($customerLink?->phone_primary ?: null),
            'tax_id' => $taxId,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function resolvePaymentMethodPayload(string $method, array $payload): array
    {
        if ($method === 'pix') {
            return ['type' => 'PIX'];
        }

        $encryptedCard = (string) ($payload['card']['encrypted'] ?? '');
        $holderName = trim((string) ($payload['card']['holder_name'] ?? ''));
        $holderTaxId = $this->normalizeDocument((string) ($payload['card']['holder_tax_id'] ?? ''));

        if ($encryptedCard === '') {
            throw new PaymentProviderException('pagbank.missing_card_encrypted');
        }

        if ($holderName === '') {
            throw new PaymentProviderException('pagbank.missing_card_holder_name');
        }

        if ($holderTaxId === '') {
            throw new PaymentProviderException('pagbank.missing_card_holder_tax_id');
        }

        $paymentMethod = [
            'type' => $method === 'debit_card' ? 'DEBIT_CARD' : 'CREDIT_CARD',
            'card' => [
                'encrypted' => $encryptedCard,
                'store' => false,
            ],
            'holder' => [
                'name' => $holderName,
                'tax_id' => $holderTaxId,
            ],
        ];

        if ($method === 'credit_card') {
            $installments = (int) ($payload['card']['installments'] ?? 0);

            if ($installments < 1 || $installments > 12) {
                throw new PaymentProviderException('pagbank.missing_card_installments');
            }

            $paymentMethod['installments'] = $installments;
            $paymentMethod['capture'] = true;
        }

        if ($method === 'debit_card') {
            $authenticationMethod = (array) ($payload['authentication_method'] ?? []);

            foreach (['type', 'id'] as $requiredField) {
                if (!isset($authenticationMethod[$requiredField]) || (string) $authenticationMethod[$requiredField] === '') {
                    throw new PaymentProviderException('pagbank.missing_debit_authentication');
                }
            }

            $paymentMethod['authentication_method'] = array_filter([
                'type' => (string) $authenticationMethod['type'],
                'id' => (string) $authenticationMethod['id'],
            ], static fn ($value) => $value !== null && $value !== '');

            $paymentMethod['installments'] = 1;
        }

        return $paymentMethod;
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

    private function sdkClient(): PendingRequest
    {
        $environment = (string) config('services.pagbank.environment', 'sandbox');
        $baseUrl = $environment === 'production' ? self::SDK_BASE_URL_PRODUCTION : self::SDK_BASE_URL_SANDBOX;
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
    private function assertSuccessful(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        throw new PaymentProviderException('pagbank.request_failed');
    }

    private function normalizeDocument(?string $document): string
    {
        return preg_replace('/\D+/', '', (string) $document) ?? '';
    }
}
