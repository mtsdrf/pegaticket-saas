<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\DTOs\Payment\PagBankChargeRequestDTO;
use App\DTOs\Payment\PagBankChargeResponseDTO;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\FinalCustomer\FinalCustomerTenantLink;
use App\Models\Finance\PlatformFinanceSettings;
use App\Models\Sale\Sale;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Models\Tenant\TenantSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    public function __construct(
        private PagBankTransactionLogger $transactionLogger,
    ) {}

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
        $credentials = $this->resolveOrderCredentials($order);
        $request = PagBankChargeRequestDTO::fromArray([
            'reference_id' => (string) $order->uuid,
            'amount' => Money::normalize((string) $order->total_amount),
            'method' => $method,
            'payer' => $this->resolveOrderPayer($order, $payload),
            'payment_method' => $this->resolvePaymentMethodPayload($method, $payload),
        ]);

        return $this->chargeAndPersist($order, $request, $credentials);
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
        $orderId = (string) $payment->provider_charge_id;
        $tenantId = $payment->payable instanceof Sale ? (int) $payment->payable->tenant_id : null;
        $credentials = $this->resolveCredentials($tenantId);
        $token = $credentials['token'];

        if ($token === '' || $orderId === '') {
            // Sem credencial ou sem id de venda remoto (cobrança nasceu
            // pending local, nunca chegou a existir no PagBank) — só
            // registra a intenção, mesma postura do ManualPaymentProvider.
            return ['status' => 'requested', 'amount' => $refundAmount];
        }

        try {
            $chargeId = $this->resolveChargeId($orderId, $credentials);

            if ($chargeId === null) {
                // Venda ainda não tem charge paga no PagBank (nunca foi
                // pago) — nada a estornar de fato.
                return ['status' => 'requested', 'amount' => $refundAmount];
            }

            $body = array_filter([
                'amount' => ['value' => Money::toMinor($refundAmount)],
            ]);

            $response = $this->client($credentials)->post("/charges/{$chargeId}/cancel", $body);
            $this->assertSuccessful($response, 'cancelCharge');

            $this->transactionLogger->info('pagbank.cancel_charge.response', [
                'order_id' => $orderId,
                'charge_id' => $chargeId,
                'payment_uuid' => $payment->uuid,
                'sale_uuid' => $payment->payable instanceof Sale ? $payment->payable->uuid : null,
                'http_status' => $response->status(),
                'response_body' => $response->json(),
            ]);

            $status = (string) $response->json('status', '');

            return [
                'status' => $status === 'CANCELED' ? 'refunded' : 'requested',
                'amount' => $refundAmount,
            ];
        } catch (PaymentProviderException $e) {
            $this->transactionLogger->error('pagbank.cancel_charge.failed', [
                'order_id' => $orderId,
                'payment_uuid' => $payment->uuid,
                'sale_uuid' => $payment->payable instanceof Sale ? $payment->payable->uuid : null,
                'exception' => $e,
            ]);

            return ['status' => 'requested', 'amount' => $refundAmount, 'error' => $e->getMessage()];
        }
    }

    public function validateWebhook(Request $request): bool
    {
        return $this->validateWebhookWithToken(
            $request,
            $this->resolveCredentials(null)['token']
        );
    }

    public function validateWebhookWithToken(Request $request, ?string $tokenOverride = null): bool
    {
        $token = $tokenOverride ?? $this->resolveCredentials(null)['token'];
        $header = (string) $request->header('x-authenticity-token', '');

        if ($token === '' || $header === '') {
            return false;
        }

        $rawPayload = $request->getContent();
        $expected = hash('sha256', $token.'-'.$rawPayload);

        return hash_equals($expected, $header);
    }

    public function getWebhookTokenForTenant(?int $tenantId): string
    {
        return $this->resolveCredentials($tenantId)['token'];
    }

    public function getPayment(string $providerChargeId): array
    {
        return $this->getPaymentForTenant($providerChargeId, null);
    }

    public function getPaymentForTenant(string $providerChargeId, ?int $tenantId): array
    {
        $credentials = $this->resolveCredentials($tenantId);
        $token = $credentials['token'];

        if ($token === '' || $providerChargeId === '') {
            return ['provider_charge_id' => $providerChargeId, 'status' => 'pending'];
        }

        try {
            $response = $this->client($credentials)->get("/orders/{$providerChargeId}");
            $this->assertSuccessful($response, 'getOrder');

            $this->transactionLogger->info('pagbank.get_order.response', [
                'order_id' => $providerChargeId,
                'http_status' => $response->status(),
                'response_body' => $response->json(),
            ]);

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
                'charge_id' => $firstCharge['id'] ?? null,
                'paid_at' => $firstCharge['paid_at'] ?? null,
                'raw_payload' => $response->json(),
            ];
        } catch (PaymentProviderException $e) {
            $this->transactionLogger->error('pagbank.get_order.failed', [
                'order_id' => $providerChargeId,
                'exception' => $e,
            ]);

            return ['provider_charge_id' => $providerChargeId, 'status' => 'pending'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSplit(string $splitId): array
    {
        $credentials = $this->resolveCredentials(null);

        if ($splitId === '' || $credentials['token'] === '') {
            return ['id' => $splitId, 'receivers' => []];
        }

        $response = $this->client($credentials)->get("/splits/{$splitId}");
        $this->assertSuccessful($response, 'getSplit');

        $this->transactionLogger->info('pagbank.get_split.response', [
            'split_id' => $splitId,
            'http_status' => $response->status(),
            'response_body' => $response->json(),
        ]);

        return (array) $response->json();
    }

    /**
     * @param  array<int, string>  $receiverAccountIds
     */
    public function releaseSplitCustody(string $splitId, array $receiverAccountIds): bool
    {
        $credentials = $this->resolveCredentials(null);

        if ($splitId === '' || $credentials['token'] === '' || $receiverAccountIds === []) {
            return false;
        }

        $body = [
            'receivers' => array_map(
                static fn (string $accountId) => [
                    'account' => [
                        'id' => $accountId,
                    ],
                ],
                array_values(array_unique(array_filter($receiverAccountIds)))
            ),
        ];

        $response = $this->client($credentials)->post("/splits/{$splitId}/custody/release", $body);
        $this->assertSuccessful($response, 'releaseSplitCustody');

        $this->transactionLogger->info('pagbank.release_split_custody.response', [
            'split_id' => $splitId,
            'request_body' => $body,
            'http_status' => $response->status(),
        ]);

        return true;
    }

    public function getCheckoutConfig(?Sale $order = null): array
    {
        $credentials = $order !== null
            ? $this->resolveOrderCredentials($order)
            : $this->resolveCredentials(null);
        $token = $credentials['token'];
        $environment = $credentials['environment'];

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

        $publicKeyResponse = $this->client($credentials)->post('/public-keys', ['type' => 'card']);
        $this->assertSuccessful($publicKeyResponse, 'createPublicKey');

        $sessionResponse = $this->sdkClient($credentials)->post('/checkout-sdk/sessions');
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
            'PAID' => 'paid',
            'AUTHORIZED' => 'authorized',
            'IN_ANALYSIS' => 'in_analysis',
            'DECLINED' => 'failed',
            'CANCELED' => 'canceled',
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
    private function resolveChargeId(string $orderId, array $credentials): ?string
    {
        $response = $this->client($credentials)->get("/orders/{$orderId}");
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
    private function chargeAndPersist(Model $payable, PagBankChargeRequestDTO $request, array $credentials): array
    {
        $response = $this->callPagBank($request, $credentials);

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
    private function callPagBank(PagBankChargeRequestDTO $request, array $credentials): PagBankChargeResponseDTO
    {
        $token = $credentials['token'];

        if ($token === '') {
            return PagBankChargeResponseDTO::fromArray([
                'id' => '',
                'status' => 'pending',
                'method' => $request->method,
                'metadata' => ['note' => 'pagbank_stub_no_credentials'],
            ]);
        }

        $amountCents = Money::toMinor($request->amount);
        $sale = Sale::query()
            ->where('uuid', $request->referenceId)
            ->with(['tenant', 'items.ticketType', 'items.eventProduct'])
            ->first();
        $split = $sale !== null ? $this->buildSplitPayload($sale, $amountCents) : null;
        $description = $this->buildSaleDescription($sale, $request->referenceId);
        $items = $this->buildSaleItemsPayload($sale, $request->referenceId, $amountCents);

        $body = array_filter([
            'reference_id' => $request->referenceId,
            'customer' => array_filter([
                'name' => $request->payer['name'] ?? null,
                'email' => $request->payer['email'] ?? null,
                'tax_id' => $request->payer['tax_id'] ?? null,
                'phones' => ! empty($request->payer['phone'])
                    ? [[
                        'country' => '55',
                        'area' => substr($this->normalizeDocument((string) $request->payer['phone']), 0, 2),
                        'number' => substr($this->normalizeDocument((string) $request->payer['phone']), 2),
                        'type' => 'MOBILE',
                    ]]
                    : null,
            ]) ?: null,
            'items' => $items,
            'qr_codes' => $request->method === 'pix'
                ? [[
                    'amount' => ['value' => $amountCents],
                    'splits' => $split,
                ]]
                : null,
            'charges' => $request->method !== 'pix'
                ? [[
                    'reference_id' => 'charge-'.Str::lower(Str::random(12)),
                    'description' => $description,
                    'amount' => [
                        'value' => $amountCents,
                        'currency' => 'BRL',
                    ],
                    'payment_method' => $request->paymentMethod,
                    'splits' => $split,
                ]]
                : null,
            'notification_urls' => [url('/api/v1/webhooks/payments/pagbank')],
        ]);

        try {
            $response = $this->client($credentials)->post('/orders', $body);
            $this->assertSuccessful($response, 'createOrder');

            $this->transactionLogger->info('pagbank.create_order.response', [
                'reference_id' => $request->referenceId,
                'request_body' => $body,
                'http_status' => $response->status(),
                'response_body' => $response->json(),
            ]);

            $firstCharge = $response->json('charges.0', []);
            $qrCode = $response->json('qr_codes.0', []);
            $chargeStatus = (string) ($firstCharge['status'] ?? '');
            $splitId = $this->extractSplitIdFromResponse($response->json());
            $splitReleaseScheduled = null;

            if ($split !== null) {
                $lastReceiver = $split['receivers'][count($split['receivers']) - 1] ?? null;
                $splitReleaseScheduled = $lastReceiver['configurations']['custody']['release']['scheduled'] ?? null;
            }

            return PagBankChargeResponseDTO::fromArray([
                'id' => $response->json('id', ''),
                'status' => $request->method === 'pix' ? 'pending' : $this->mapStatus($chargeStatus),
                'method' => $request->method,
                'metadata' => [
                    'qr_code_text' => $qrCode['text'] ?? null,
                    'qr_code_id' => $qrCode['id'] ?? null,
                    'expiration_date' => $qrCode['expiration_date'] ?? null,
                    'provider_status' => $chargeStatus !== '' ? $chargeStatus : ($request->method === 'pix' ? 'WAITING' : null),
                    'raw_status' => $chargeStatus !== '' ? $chargeStatus : null,
                    'charge_id' => $firstCharge['id'] ?? null,
                    'split_id' => $splitId,
                    'split_release_scheduled' => $splitReleaseScheduled,
                    'paid_at' => $firstCharge['paid_at'] ?? null,
                    'payment_response' => $firstCharge['payment_response'] ?? null,
                    'card' => $firstCharge['payment_method']['card'] ?? null,
                    'provider_response' => $response->json(),
                ],
            ]);
        } catch (PaymentProviderException $e) {
            $this->transactionLogger->error('pagbank.create_order.failed', [
                'reference_id' => $request->referenceId,
                'request_body' => $body,
                'exception' => $e,
            ]);

            return PagBankChargeResponseDTO::fromArray([
                'id' => '',
                'status' => 'failed',
                'method' => $request->method,
                'metadata' => ['note' => 'pagbank_order_creation_failed'],
            ]);
        }
    }

    private function buildSaleDescription(?Sale $sale, string $referenceId): string
    {
        if ($sale === null) {
            return 'Compra ingresso + '.$referenceId;
        }

        $itemLabel = $sale->items
            ->map(fn ($item) => $item->ticketType?->name ?? $item->eventProduct?->name)
            ->filter()
            ->first() ?? 'ingresso';

        $tenantName = $sale->tenant?->name ?? 'PegaTicket';

        return Str::limit("Compra {$itemLabel} + {$tenantName}", 120, '');
    }

    /**
     * @return array<int, array{name: string, quantity: int, unit_amount: int}>
     */
    private function buildSaleItemsPayload(?Sale $sale, string $referenceId, int $fallbackAmountCents): array
    {
        if ($sale === null || $sale->items->isEmpty()) {
            return [[
                'name' => 'Compra ingresso',
                'quantity' => 1,
                'unit_amount' => $fallbackAmountCents,
            ]];
        }

        return $sale->items
            ->map(function ($item) {
                $name = $item->ticketType?->name ?? $item->eventProduct?->name ?? 'Item da compra';
                $quantity = max(1, (int) round((float) $item->quantity));
                $unitAmount = Money::toMinor((string) $item->unit_price);

                return [
                    'name' => Str::limit($name, 120, ''),
                    'quantity' => $quantity,
                    'unit_amount' => $unitAmount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSplitPayload(Sale $sale, int $grossAmountCents): ?array
    {
        $settings = $this->resolveSplitSettings((int) $sale->tenant_id);

        if ($settings === null) {
            return null;
        }

        $platformFeeAmountCents = min($grossAmountCents, Money::toMinor((string) $settings['platform_fee_fixed_amount']));
        $tenantNetAmountCents = max(0, $grossAmountCents - $platformFeeAmountCents);
        $releaseScheduled = $this->resolveSplitReleaseAt($sale, (int) $settings['default_settlement_offset_days']);

        $receivers = [];

        if ($platformFeeAmountCents > 0) {
            $receivers[] = [
                'account' => [
                    'id' => $settings['platform_account_id'],
                ],
                'amount' => [
                    'value' => $platformFeeAmountCents,
                ],
                'reason' => 'Taxa fixa da plataforma',
                'configurations' => [
                    'custody' => [
                        'apply' => false,
                    ],
                ],
            ];
        }

        $tenantReceiver = [
            'account' => [
                'id' => $settings['tenant_account_id'],
            ],
            'amount' => [
                'value' => $tenantNetAmountCents,
            ],
            'reason' => 'Repasse do organizador',
            'configurations' => [
                'custody' => [
                    'apply' => (bool) $settings['split_custody_enabled'],
                ],
            ],
        ];

        if ((bool) $settings['split_custody_enabled']) {
            $tenantReceiver['configurations']['custody']['release'] = [
                'scheduled' => $releaseScheduled,
            ];
        }

        $receivers[] = $tenantReceiver;

        return [
            'method' => 'FIXED',
            'receivers' => $receivers,
        ];
    }

    /**
     * @return array{platform_account_id:string,tenant_account_id:string,platform_fee_fixed_amount:string,default_settlement_offset_days:int,split_custody_enabled:bool}|null
     */
    private function resolveSplitSettings(int $tenantId): ?array
    {
        $platformSettings = PlatformFinanceSettings::query()
            ->whereNull('deleted_at')
            ->first();

        $tenantSettings = TenantSettings::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        $platformAccountId = trim((string) ($platformSettings?->pagbank_primary_account_id ?? ''));
        $tenantAccountId = trim((string) ($tenantSettings?->pagbank_receiver_account_id ?? ''));

        if ($platformAccountId === '' || $tenantAccountId === '') {
            return null;
        }

        return [
            'platform_account_id' => $platformAccountId,
            'tenant_account_id' => $tenantAccountId,
            'platform_fee_fixed_amount' => (string) ($platformSettings?->platform_fee_fixed_amount ?? '0'),
            'default_settlement_offset_days' => (int) ($platformSettings?->default_settlement_offset_days ?? 1),
            'split_custody_enabled' => (bool) ($platformSettings?->split_custody_enabled ?? true),
        ];
    }

    /**
     * @return array{token:string,environment:string}
     */
    private function resolveOrderCredentials(Sale $order): array
    {
        if ($this->resolveSplitSettings((int) $order->tenant_id) !== null) {
            return $this->resolveCredentials(null);
        }

        return $this->resolveCredentials((int) $order->tenant_id);
    }

    private function resolveSplitReleaseAt(Sale $sale, int $offsetDays): string
    {
        $sale->loadMissing([
            'items.ticketType.event',
            'items.eventProduct.event',
        ]);

        $eventEnd = $sale->items
            ->map(fn ($item) => $item->ticketType?->event?->ends_at ?? $item->eventProduct?->event?->ends_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $endsAt) => $endsAt->getTimestamp())
            ->first();

        $baseDate = $eventEnd instanceof CarbonInterface
            ? $eventEnd->copy()
            : ($sale->paid_at ?? now())->copy();

        return $baseDate
            ->addDays(max(1, $offsetDays))
            ->startOfDay()
            ->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractSplitIdFromResponse(array $payload): ?string
    {
        foreach (Arr::wrap(data_get($payload, 'charges.0.links', [])) as $link) {
            if (($link['rel'] ?? null) !== 'SPLIT') {
                continue;
            }

            $href = (string) ($link['href'] ?? '');

            if (preg_match('/(SPLI_[A-Z0-9_-]+)/', $href, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
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
     * @param  array<string, mixed>  $payload
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
                if (! isset($authenticationMethod[$requiredField]) || (string) $authenticationMethod[$requiredField] === '') {
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
    private function client(array $credentials = []): PendingRequest
    {
        $environment = (string) ($credentials['environment'] ?? config('services.pagbank.environment', 'sandbox'));
        $baseUrl = $environment === 'production' ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
        $token = (string) ($credentials['token'] ?? config('services.pagbank.token', ''));

        return Http::withToken($token)
            ->baseUrl($baseUrl)
            ->timeout(15);
    }

    private function sdkClient(array $credentials = []): PendingRequest
    {
        $environment = (string) ($credentials['environment'] ?? config('services.pagbank.environment', 'sandbox'));
        $baseUrl = $environment === 'production' ? self::SDK_BASE_URL_PRODUCTION : self::SDK_BASE_URL_SANDBOX;
        $token = (string) ($credentials['token'] ?? config('services.pagbank.token', ''));

        return Http::withToken($token)
            ->baseUrl($baseUrl)
            ->timeout(15);
    }

    /**
     * @return array{token:string,environment:string}
     */
    private function resolveCredentials(?int $tenantId): array
    {
        $fallbackEnvironment = (string) config('services.pagbank.environment', 'sandbox');
        $fallbackToken = (string) config('services.pagbank.token', '');

        if ($tenantId === null) {
            return [
                'token' => $fallbackToken,
                'environment' => $fallbackEnvironment,
            ];
        }

        $settings = TenantSettings::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first();

        if (
            $settings === null
            || ($settings->pagbank_integration_mode ?? 'disabled') !== 'manual_token'
            || empty($settings->pagbank_access_token)
        ) {
            return [
                'token' => $fallbackToken,
                'environment' => $fallbackEnvironment,
            ];
        }

        return [
            'token' => (string) $settings->pagbank_access_token,
            'environment' => (string) ($settings->pagbank_environment ?: $fallbackEnvironment),
        ];
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
