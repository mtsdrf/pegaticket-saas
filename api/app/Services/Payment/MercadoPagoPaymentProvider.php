<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Exceptions\Payment\PaymentOperationInProgressException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\Sale\Sale;
use App\Models\Payment\PaymentIdempotencyKey;
use App\Models\Subscription\Invoice;
use App\Models\Subscription\Payment;
use App\Models\Subscription\Subscription;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;
use App\Repositories\Contracts\TenantUserRepositoryInterface;
use App\Support\Money;
use App\Support\Payment\IdempotencyOutcome;
use App\Services\Logging\ApplicationLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Adapter Mercado Pago (roadmap Fase B, item 1 — PSP real). Chama a API
 * REST do Mercado Pago diretamente via Http facade (sem SDK — mesmo padrão
 * já usado no projeto para HTTP externo, ver ReverseGeocodeService), o que
 * dá controle total de timeout/log/retry em hospedagem compartilhada e
 * evita mais uma dependência de composer.
 *
 * PCI: o backend NUNCA recebe dado de cartão cru — createCardCharge recebe
 * só o token já gerado pelo MP.js no frontend. Nenhum access_token/payload
 * completo é logado (ver sanitizeForLog); apenas o necessário para debug.
 */
class MercadoPagoPaymentProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public function __construct(
        private FinalCustomerTenantLinkRepositoryInterface $finalCustomerTenantLinkRepository,
        private TenantUserRepositoryInterface $tenantUserRepository,
        private IdempotencyRepositoryInterface $idempotencyRepository,
    ) {
    }

    /**
     * billing_period do plan_price -> [frequency, frequency_type] do
     * Preapproval do Mercado Pago (frequency_type sempre 'months', o MP não
     * tem tipo 'years').
     */
    private const FREQUENCY_BY_PERIOD = [
        'monthly' => [1, 'months'],
        'quarterly' => [3, 'months'],
        'yearly' => [12, 'months'],
    ];

    public function createPixCharge(Invoice $invoice): array
    {
        return $this->createOrder($invoice, (string) $invoice->amount_net, 'pix', [
            'id' => 'pix',
            'type' => 'bank_transfer',
        ], $this->resolveTenantPayer((int) $invoice->tenant_id));
    }

    public function createPixChargeForOrder(Sale $order): array
    {
        return $this->createOrder($order, (string) $order->total_amount, 'pix', [
            'id' => 'pix',
            'type' => 'bank_transfer',
        ], $this->resolveOrderPayer($order));
    }

    public function createCardCharge(Invoice $invoice, array $cardToken): array
    {
        $token = (string) ($cardToken['token'] ?? '');

        if ($token === '') {
            throw new PaymentProviderException('mercadopago.missing_card_token');
        }

        $amount = (string) $invoice->amount_net;

        $payerEmail = $cardToken['payer_email'] ?? null;
        $payer = $payerEmail !== null && $payerEmail !== ''
            ? ['email' => $payerEmail]
            : $this->resolveTenantPayer((int) $invoice->tenant_id);

        return $this->createOrder($invoice, $amount, 'card', [
            'id' => (string) ($cardToken['payment_method_id'] ?? ''),
            'type' => 'credit_card',
            'token' => $token,
            'installments' => (int) ($cardToken['installments'] ?? 1),
        ], $payer);
    }

    /**
     * Cria o Preapproval (assinatura automática recorrente) no Mercado
     * Pago: o tenant autoriza 1x (cartão salvo no MP) e o MP cobra sozinho
     * todo ciclo, conforme `auto_recurring.frequency`/`frequency_type`
     * derivados de `billing_period` do plan_price. `back_url` é obrigatório
     * pela API do MP mas não é usado nesta onda (checkout embutido ainda
     * não existe) — aponta para a tela de assinatura do painel.
     */
    /**
     * `$operationPrefix` distingue a identidade lógica da idempotência
     * entre a criação inicial da assinatura ("preapproval_create") e a
     * troca de plano de uma assinatura já existente ("preapproval_change")
     * — SubscriptionService::create()/changePlan() passam o prefixo
     * correto explicitamente (nunca inferido do estado do model, que seria
     * ambíguo entre os dois fluxos).
     *
     * `$cardTokenId` (decisão de produto 2026-07-24 — eliminar o
     * redirecionamento ao ambiente do Mercado Pago): quando informado, o
     * cartão (já tokenizado pelo MP.js no navegador — nunca dado cru) é
     * enviado junto com `status: authorized`, criando E autorizando o
     * Preapproval na mesma chamada síncrona (confirmado na doc oficial de
     * assinaturas sem plano associado). Sem `card_token_id`, mantém o
     * comportamento legado (`status: pending`, sem cartão) — não é mais o
     * caminho usado pelo fluxo normal, preservado só como fallback.
     */
    public function createPreapproval(Subscription $subscription, string $operationPrefix = 'preapproval_create', ?string $cardTokenId = null): array
    {
        $planPrice = $subscription->planPrice()->withTrashed()->firstOrFail();

        // payer_email é obrigatório para POST /preapproval. Decisão de
        // negócio: quem paga a mensalidade é sempre o PROPRIETÁRIO do
        // tenant (User, via tenant_users+tenant_roles.slug=owner) — nunca
        // a empresa (tenants.email é contato opcional do estabelecimento,
        // dado diferente) nem o usuário autenticado no momento da chamada
        // (pode ser um funcionário com permissão trocando o plano, não o
        // dono). Sem owner resolvível, falha explícita — nunca envia
        // payer_email nulo/inventado.
        $payerEmail = $this->resolveOwnerEmail((int) $subscription->tenant_id);

        if ($payerEmail === null || $payerEmail === '') {
            throw new PaymentProviderException('mercadopago.missing_payer_email');
        }

        [$frequency, $frequencyType] = self::FREQUENCY_BY_PERIOD[$subscription->billing_period] ?? [1, 'months'];

        $operation = $operationPrefix . ':' . $subscription->uuid;
        $idempotency = $this->acquireIdempotency((int) $subscription->tenant_id, $operation);

        $payload = [
            'reason' => 'Assinatura PegaTicket — ' . ($subscription->plan?->name ?? $subscription->billing_period),
            'external_reference' => $subscription->uuid,
            'payer_email' => $payerEmail,
            'back_url' => rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/subscription',
            'auto_recurring' => [
                'frequency' => $frequency,
                'frequency_type' => $frequencyType,
                'transaction_amount' => Money::normalize((string) $planPrice->amount),
                'currency_id' => 'BRL',
                // Mercado Pago exige milissegundos no formato ISO 8601 pra
                // auto_recurring.start_date/end_date — sem eles, a API
                // responde 400 "Invalid format" (confirmado empiricamente
                // contra o sandbox real; Carbon::toAtomString() omite 'v').
                'start_date' => ($subscription->trial_ends_at ?? now())->format('Y-m-d\TH:i:s.vP'),
            ],
            'status' => $cardTokenId !== null ? 'authorized' : 'pending',
        ];

        if ($cardTokenId !== null) {
            $payload['card_token_id'] = $cardTokenId;
        }

        $response = $this->callWithIdempotencyGuard(
            $idempotency,
            fn () => $this->mutatingClient($idempotency->key())->post('/preapproval', $payload),
            'createPreapproval'
        );

        $body = $response->json();

        $result = [
            'preapproval_id' => (string) ($body['id'] ?? ''),
            'status' => (string) ($body['status'] ?? 'pending'),
            'checkout_url' => $body['init_point'] ?? null,
        ];

        // Um card_token_id foi enviado exigindo status:authorized — se o
        // Mercado Pago não confirmar exatamente esse status na resposta
        // (cartão recusado, pendência de análise etc.), a autorização do
        // cartão falhou. Nunca persiste uma assinatura pending/mal-
        // autorizada como se fosse sucesso.
        if ($cardTokenId !== null && $result['status'] !== 'authorized') {
            ApplicationLogger::error('Cartão não autorizado ao criar Preapproval no Mercado Pago', [
                'subscription_uuid' => $subscription->uuid,
                'preapproval_id' => $result['preapproval_id'],
                'status' => $result['status'],
            ]);

            $this->idempotencyRepository->markFailed($idempotency->record);

            throw new PaymentProviderException('mercadopago.card_authorization_failed');
        }

        $this->idempotencyRepository->markSucceeded($idempotency->record, [
            'preapproval_id' => $result['preapproval_id'],
            'status' => $result['status'],
        ]);

        return $result;
    }

    public function cancelPreapproval(Subscription $subscription): array
    {
        if ($subscription->preapproval_id === null) {
            return ['status' => 'not_applicable'];
        }

        $response = $this->mutatingClient()->put(
            "/preapproval/{$subscription->preapproval_id}",
            ['status' => 'cancelled']
        );

        $this->assertSuccessful($response, 'cancelPreapproval');

        return ['status' => (string) ($response->json('status') ?? 'cancelled')];
    }

    /**
     * Troca o cartão de uma assinatura recorrente automática já existente
     * (`PUT /preapproval/{id}`, mesmo verbo/endpoint já usado por
     * cancelPreapproval — só muda o corpo). `card_token_id` é o token já
     * gerado pelo MP.js no frontend (nunca dado de cartão cru — PCI), mesmo
     * padrão de createCardCharge. Campo documentado pela API de Assinaturas
     * do Mercado Pago para troca de meio de pagamento; não pôde ser
     * validado contra uma chamada real nesta sessão (mesma limitação de
     * credenciais de teste já registrada nas auditorias anteriores) —
     * primeiro ponto a revisar se o formato real divergir.
     */
    public function updatePreapprovalPaymentMethod(Subscription $subscription, array $cardToken): array
    {
        if ($subscription->preapproval_id === null) {
            throw new PaymentProviderException('mercadopago.missing_preapproval_id');
        }

        $token = (string) ($cardToken['token'] ?? '');

        if ($token === '') {
            throw new PaymentProviderException('mercadopago.missing_card_token');
        }

        $response = $this->mutatingClient()->put(
            "/preapproval/{$subscription->preapproval_id}",
            ['card_token_id' => $token]
        );

        $this->assertSuccessful($response, 'updatePreapprovalPaymentMethod');

        return ['status' => (string) ($response->json('status') ?? 'updated')];
    }

    public function refund(Payment $payment, string|int|float|null $amount = null): array
    {
        if ($payment->provider_charge_id === null) {
            throw new PaymentProviderException('mercadopago.missing_provider_charge_id');
        }

        $refundAmount = $amount !== null ? Money::normalize($amount) : null;

        // Chave de idempotência derivada de (payment, amount) — não um uuid
        // novo a cada chamada. Estorno é uma operação financeira única; sem
        // isso, um retry (timeout ambíguo, job de fila reprocessando) criaria
        // um SEGUNDO estorno real no Mercado Pago. Reexecutar o estorno do
        // MESMO pagamento com o MESMO valor sempre reusa a mesma chave.
        $idempotencyKey = 'refund:' . $payment->uuid . ':' . ($refundAmount ?? Money::normalize((string) $payment->amount));

        // Estorno TOTAL: corpo vazio "{}" (doc oficial) — json_encode([]) do
        // Laravel gera "[]", não "{}", por isso o corpo é enviado como raw
        // body aqui em vez de array, para casar exatamente com o documentado.
        if ($refundAmount === null) {
            $response = $this->mutatingClient($idempotencyKey)
                ->withBody('{}', 'application/json')
                ->post("/v1/orders/{$payment->provider_charge_id}/refund");
        } else {
            // Estorno PARCIAL exige o transaction_id da order (não o id da
            // order em si) — capturado em metadata na criação da cobrança
            // (extractMetadata). Sem ele, não é seguro seguir (não
            // inventar/adivinhar o id).
            $transactionId = (string) ($payment->metadata['transaction_id'] ?? '');

            if ($transactionId === '') {
                throw new PaymentProviderException('mercadopago.missing_transaction_id_for_partial_refund');
            }

            $response = $this->mutatingClient($idempotencyKey)->post(
                "/v1/orders/{$payment->provider_charge_id}/refund",
                ['transaction_id' => $transactionId, 'amount' => $refundAmount]
            );
        }

        $this->assertSuccessful($response, 'refund');

        $data = $response->json();

        return [
            'status' => 'requested',
            'amount' => $refundAmount ?? Money::normalize((string) $payment->amount),
            'provider_refund_id' => $data['id'] ?? null,
        ];
    }

    /**
     * Valida o header `x-signature` (HMAC-SHA256) enviado pelo Mercado Pago
     * desde 2024. Manifest oficial:
     * "id:{data.id};request-id:{x-request-id};ts:{ts};" (data.id em
     * minúsculo quando vier como query string `data.id`). Comparação em
     * tempo constante (hash_equals) — nunca early-return em cada byte.
     */
    public function validateWebhook(Request $request): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signatureHeader = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');

        if ($signatureHeader === '' || $requestId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            [$key, $value] = array_pad(explode('=', trim($chunk), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if ($ts === null || $v1 === null) {
            return false;
        }

        // Replay: notificação com timestamp fora de uma janela razoável é
        // rejeitada mesmo com assinatura tecnicamente válida.
        if (abs(time() - (int) $ts) > 300) {
            return false;
        }

        // A documentação oficial do Mercado Pago instrui converter data.id
        // para minúsculas antes de montar o manifest quando ele vier com
        // letras maiúsculas (alguns IDs alfanuméricos, ex. preapproval). Não
        // pôde ser validado contra uma notificação real nesta sessão
        // (credenciais de teste ainda pendentes de ativação no painel MP) —
        // aplicado por segurança/conformidade com a doc, sinalizado no
        // relatório de auditoria.
        $dataId = strtolower((string) ($request->query('data.id') ?? $request->input('data.id') ?? ''));

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }

    /**
     * Consulta um "authorized payment" (cobrança de ciclo de um Preapproval)
     * no Mercado Pago — é o que o webhook `subscription_authorized_payment`
     * referencia em `data.id`. `status` documentado pelo MP:
     * pending|scheduled|processed|rejected|cancelled.
     *
     * @return array<string, mixed>
     */
    public function getAuthorizedPayment(string $authorizedPaymentId): array
    {
        $response = $this->queryClient()->get("/authorized_payments/{$authorizedPaymentId}");

        $this->assertSuccessful($response, 'getAuthorizedPayment');

        $body = $response->json();

        return [
            'preapproval_id' => $body['preapproval_id'] ?? null,
            'status' => $body['status'] ?? null,
            'amount' => Money::normalize((string) ($body['transaction_amount'] ?? 0)),
        ];
    }

    /**
     * Consulta uma assinatura/preapproval diretamente no Mercado Pago
     * (`GET /preapproval/{id}`) para reconciliação ativa do vínculo
     * recorrente, independente dos webhooks de cobrança de ciclo.
     *
     * @return array<string, mixed>
     */
    public function getPreapproval(string $preapprovalId): array
    {
        $response = $this->queryClient()->get("/preapproval/{$preapprovalId}");

        $this->assertSuccessful($response, 'getPreapproval');

        $body = $response->json();

        return [
            'preapproval_id' => (string) ($body['id'] ?? $preapprovalId),
            'status' => (string) ($body['status'] ?? ''),
            'external_reference' => $body['external_reference'] ?? null,
            'next_payment_date' => $body['next_payment_date'] ?? null,
        ];
    }

    /**
     * Busca um preapproval por `external_reference` (`GET /preapproval/
     * search`) — usado SOMENTE pela reconciliação de idempotência
     * (`payments:reconcile-idempotency`) quando uma tentativa `pending`
     * expirou sem confirmação (timeout ambíguo real): decide se o Mercado
     * Pago efetivamente criou o recurso do lado dele antes de permitir
     * qualquer nova tentativa. Best-effort/não confirmado contra uma
     * notificação real de sandbox nesta sessão (mesma ressalva já
     * registrada para outros endpoints de busca deste adapter) — primeiro
     * ponto a revisar se o formato divergir.
     *
     * @return array<string, mixed>|null
     */
    public function searchPreapprovalByExternalReference(string $externalReference): ?array
    {
        $response = $this->queryClient()->get('/preapproval/search', [
            'external_reference' => $externalReference,
        ]);

        $this->assertSuccessful($response, 'searchPreapprovalByExternalReference');

        $results = $response->json('results') ?? [];
        $first = is_array($results) ? ($results[0] ?? null) : null;

        if (! is_array($first)) {
            return null;
        }

        return [
            'preapproval_id' => (string) ($first['id'] ?? ''),
            'status' => (string) ($first['status'] ?? ''),
            'external_reference' => $first['external_reference'] ?? null,
        ];
    }

    /**
     * Busca uma order por `external_reference` (`GET /v1/orders/search`) —
     * mesmo propósito de `searchPreapprovalByExternalReference`, aplicado
     * ao fluxo de cobrança de pedido/fatura.
     *
     * @return array<string, mixed>|null
     */
    public function searchOrderByExternalReference(string $externalReference): ?array
    {
        $response = $this->queryClient()->get('/v1/orders/search', [
            'external_reference' => $externalReference,
        ]);

        $this->assertSuccessful($response, 'searchOrderByExternalReference');

        $results = $response->json('elements') ?? $response->json('results') ?? [];
        $first = is_array($results) ? ($results[0] ?? null) : null;

        if (! is_array($first)) {
            return null;
        }

        $transaction = $first['transactions']['payments'][0] ?? [];

        return [
            'provider_charge_id' => (string) ($first['id'] ?? ''),
            'status' => $this->mapStatus($transaction['status'] ?? $first['status'] ?? null),
            'amount' => Money::normalize((string) ($first['total_amount'] ?? 0)),
        ];
    }

    /**
     * Consulta um chargeback diretamente no Mercado Pago a partir do id
     * notificado no webhook opcional `topic_chargebacks_wh`.
     *
     * @return array<string, mixed>
     */
    public function getChargeback(string $chargebackId): array
    {
        $response = $this->queryClient()->get("/v1/chargebacks/{$chargebackId}");

        $this->assertSuccessful($response, 'getChargeback');

        $body = $response->json();
        $payments = $body['payments'] ?? [];

        return [
            'id' => (string) ($body['id'] ?? $chargebackId),
            'payments' => is_array($payments) ? $payments : [$payments],
            'amount' => Money::normalize((string) ($body['amount'] ?? 0)),
            'currency' => $body['currency'] ?? null,
            // Não confirmado contra a doc pública vigente (não há chave de
            // prazo de contestação documentada para este endpoint no
            // material consultado) — leitura best-effort de chaves
            // plausíveis, só preenchida se o Mercado Pago realmente enviar
            // uma delas; nunca inventa uma data quando ausente.
            'resolution_date' => $this->extractResolutionDate($body),
        ];
    }

    /**
     * Consulta uma reclamação no domínio pós-venda do Mercado Pago. O
     * recurso pode apontar para shipment/payment/etc.; por isso o caller
     * decide de forma conservadora se consegue associar a algo local.
     *
     * @return array<string, mixed>
     */
    public function getClaim(string $claimId): array
    {
        $response = $this->queryClient()->get("/post-purchase/v1/claims/{$claimId}");

        $this->assertSuccessful($response, 'getClaim');

        $body = $response->json();

        return [
            'id' => (string) ($body['id'] ?? $claimId),
            'resource' => $body['resource'] ?? null,
            'resource_id' => $body['resource_id'] ?? null,
            'status' => $body['status'] ?? null,
            'type' => $body['type'] ?? null,
            // Mesma ressalva de getChargeback(): best-effort, não confirmado
            // contra a doc pública vigente para este endpoint.
            'resolution_date' => $this->extractResolutionDate($body),
        ];
    }

    /**
     * Leitura best-effort do prazo de resolução/contestação de um
     * chargeback/claim do Mercado Pago. Nenhuma das chaves abaixo foi
     * confirmada contra a documentação oficial vigente (não enumerada no
     * material disponível na consulta) — só preenche quando o payload
     * realmente trouxer uma delas; caso contrário retorna null e o dado de
     * prazo simplesmente não existe local (não é inventado).
     */
    private function extractResolutionDate(array $body): ?string
    {
        return $body['resolution_date']
            ?? $body['due_date']
            ?? $body['deadline_at']
            ?? $body['expiration_date']
            ?? null;
    }

    /**
     * Probe read-only para homologação/observabilidade. Não cria cobrança,
     * não altera assinatura e não toca recursos do cliente; valida só
     * conectividade + autenticação contra um endpoint público da API.
     *
     * @return array<string, mixed>
     */
    public function healthCheck(): array
    {
        $response = $this->baseClient()->get('/v1/payment_methods');

        return [
            'reachable' => $response->status() > 0,
            'authenticated' => $response->successful(),
            'status' => $response->status(),
            'environment' => (string) config('services.mercadopago.environment', ''),
            'payment_methods_count' => $response->successful() ? count($response->json() ?? []) : 0,
            'error' => $response->successful()
                ? null
                : ($response->json('message') ?? $response->json('error') ?? $response->body()),
        ];
    }

    /**
     * Consulta uma order (GET /v1/orders/{id}) — mantém o nome getPayment()
     * por compatibilidade com a interface/callers existentes (webhook,
     * reconciliação), mas o recurso consultado agora é a Sale, não mais um
     * Payment legado.
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $providerChargeId): array
    {
        $response = $this->queryClient()->get("/v1/orders/{$providerChargeId}");

        $this->assertSuccessful($response, 'getPayment');

        $body = $response->json();
        $transaction = $body['transactions']['payments'][0] ?? [];

        return [
            'provider_charge_id' => (string) ($body['id'] ?? $providerChargeId),
            'status' => $this->mapStatus($transaction['status'] ?? $body['status'] ?? null),
            'amount' => Money::normalize((string) ($body['total_amount'] ?? 0)),
            'raw_status' => $transaction['status'] ?? $body['status'] ?? null,
        ];
    }

    /**
     * Cria uma order (POST /v1/orders) junto ao Mercado Pago e registra o
     * Payment polimórfico local (invoice OU order) — mesmo formato de
     * retorno do ManualPaymentProvider ({payment_uuid, status, method}).
     * `$paymentMethod` é o objeto `payment_method` documentado (id/type e,
     * conforme o método, token/installments ou expiration_time).
     *
     * @param array<string, mixed> $paymentMethod
     * @param array<string, mixed> $payer objeto `payer` da API de Orders
     *        (já resolvido pelo caller — nunca vazio quando enviado, a API
     *        exige ao menos 1 propriedade preenchida em `payer`).
     * @return array<string, mixed>
     */
    private function createOrder(Model $payable, string|int|float $amount, string $method, array $paymentMethod, array $payer = []): array
    {
        $amountString = Money::normalize($amount);

        // Identidade lógica estável da operação: order_charge/invoice_charge
        // + uuid do payable (já persistido antes desta chamada em ambos os
        // casos — Sale/Invoice sempre existem antes de uma cobrança ser
        // tentada), nunca uma chave aleatória nova a cada tentativa.
        $operationPrefix = $payable instanceof Sale ? 'order_charge' : 'invoice_charge';
        $operation = $operationPrefix . ':' . $payable->uuid;
        $idempotency = $this->acquireIdempotency((int) $payable->tenant_id, $operation);

        $payload = array_filter([
            'type' => 'online',
            'processing_mode' => 'automatic',
            'total_amount' => $amountString,
            'external_reference' => (string) $payable->uuid,
            'payer' => $payer !== [] ? $payer : null,
            'transactions' => [
                'payments' => [[
                    'amount' => $amountString,
                    'payment_method' => $paymentMethod,
                ]],
            ],
        ], static fn ($value) => $value !== null);

        $response = $this->callWithIdempotencyGuard(
            $idempotency,
            fn () => $this->mutatingClient($idempotency->key())->post('/v1/orders', $payload),
            'createOrder'
        );

        $body = $response->json();

        $this->idempotencyRepository->markSucceeded($idempotency->record, [
            'provider_charge_id' => (string) ($body['id'] ?? ''),
        ]);

        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'provider' => 'mercadopago',
            'provider_charge_id' => (string) ($body['id'] ?? ''),
            'method' => $method,
            'amount' => $amountString,
            'status' => $this->mapStatus($this->extractTransactionStatus($body)),
            'idempotency_key' => $idempotency->key(),
            'metadata' => $this->extractMetadata($body),
        ]);

        return [
            'payment_uuid' => $payment->uuid,
            'status' => $payment->status,
            'method' => $method,
        ];
    }

    /**
     * Resolve o `payer` de uma cobrança de PEDIDO (cliente final → tenant).
     * `POST /v1/orders` exige ao menos 1 propriedade preenchida em `payer` —
     * `sales.final_customer_id` referencia `final_customers` diretamente
     * (FinalCustomer absorveu Client em 2026-07-31), que já tem `email`
     * (login do Portal/loja). CPF/CNPJ vem do FinalCustomerTenantLink
     * (dado por-tenant). Prioridade: e-mail do FinalCustomer > CPF/CNPJ do
     * link por-tenant (já validado 11/14 dígitos no cadastro) > nome do
     * FinalCustomer (name/last_name) como último recurso. NUNCA usa o
     * e-mail do tenant/loja aqui — o payer é o cliente final, não o
     * estabelecimento.
     *
     * @return array<string, mixed>
     */
    private function resolveOrderPayer(Sale $order): array
    {
        // finalCustomerLink NÃO entra em loadMissing() de propósito (ver
        // SaleService::EAGER_RELATIONS) — acesso abaixo via
        // $order->finalCustomerLink é lazy sobre a instância REAL de
        // $order, o único jeito correto de resolver essa relation.
        $order->loadMissing('finalCustomer');

        $customer = $order->finalCustomer;

        if ($customer === null) {
            return [];
        }

        $payer = [];

        if (!empty($customer->email)) {
            $payer['email'] = $customer->email;
        }

        $link = $order->finalCustomerLink;

        if ($link && !empty($link->cpf_cnpj)) {
            $digits = (string) $link->cpf_cnpj;

            $payer['identification'] = [
                'type' => strlen($digits) === 11 ? 'CPF' : 'CNPJ',
                'number' => $digits,
            ];
        }

        if ($payer === [] && !empty($customer->name)) {
            $payer['first_name'] = $customer->name;

            if (!empty($customer->last_name)) {
                $payer['last_name'] = $customer->last_name;
            }
        }

        return $payer;
    }

    /**
     * Resolve o `payer` de uma cobrança de FATURA (PegaTicket → tenant — Pix/
     * cartão avulso de fatura, ainda sem caller real). Mesma regra de
     * createPreapproval: o payer é sempre o PROPRIETÁRIO do tenant (User),
     * nunca o e-mail de contato do estabelecimento (tenants.email) nem o
     * usuário autenticado no momento da chamada.
     *
     * @return array<string, mixed>
     */
    private function resolveTenantPayer(int $tenantId): array
    {
        $email = $this->resolveOwnerEmail($tenantId);

        return $email !== null && $email !== '' ? ['email' => $email] : [];
    }

    /**
     * E-mail do PROPRIETÁRIO do tenant (tenant_users+tenant_roles.slug=
     * owner) — fonte única de verdade de "quem paga" a cobrança de
     * assinatura no Mercado Pago. `users.email` é obrigatório/único, então
     * quando um owner é encontrado o e-mail sempre existe; retorna null só
     * quando o tenant não tem nenhum owner ativo vinculado (estado de dados
     * inconsistente — o caller deve falhar explicitamente, nunca inventar).
     *
     * Exceção operacional deliberada: em homologação (`MERCADOPAGO_
     * ENVIRONMENT=test`) pode existir `MERCADOPAGO_TEST_PAYER_EMAIL` para
     * forçar um pagador único da conta Mercado Pago de teste. Isso evita
     * que cada empresa/local precise ter um owner que também seja conta de
     * teste do MP. Em produção, essa chave é ignorada.
     */
    private function resolveOwnerEmail(int $tenantId): ?string
    {
        if ((string) config('services.mercadopago.environment') === 'test') {
            $configuredTestEmail = trim((string) config('services.mercadopago.test_payer_email', ''));

            if ($configuredTestEmail !== '') {
                return $configuredTestEmail;
            }
        }

        return $this->tenantUserRepository->findOwnerUserForTenant($tenantId)?->email;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? $name, $parts[1] ?? null];
    }

    private function extractTransactionStatus(array $body): ?string
    {
        $transaction = $body['transactions']['payments'][0] ?? [];

        return $transaction['status'] ?? $body['status'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMetadata(array $body): array
    {
        $transaction = $body['transactions']['payments'][0] ?? [];
        $paymentMethod = $transaction['payment_method'] ?? [];

        return array_filter([
            'qr_code' => $paymentMethod['qr_code'] ?? null,
            'qr_code_base64' => $paymentMethod['qr_code_base64'] ?? null,
            'ticket_url' => $paymentMethod['ticket_url'] ?? null,
            'status_detail' => $transaction['status_detail'] ?? $body['status_detail'] ?? null,
            // Necessário para um futuro estorno PARCIAL (POST
            // /v1/orders/{id}/refund exige transaction_id, não o id da
            // order) — nunca usado/exibido além disso.
            'transaction_id' => $transaction['id'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * Mapeamento best-effort dos status documentados da API de Orders
     * (`action_required`/`processed`/`cancelled` no nível da order, e o
     * status do transactions.payments[0] quando presente — que segue o
     * vocabulário antigo approved/pending/rejected/refunded/charged_back).
     * Não confirmado contra uma notificação real de produção/sandbox nesta
     * sessão — primeiro ponto a revisar se o valor real divergir.
     */
    private function mapStatus(?string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved', 'processed' => 'paid',
            'rejected', 'cancelled' => 'failed',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending', // inclui action_required, pending, null
        };
    }

    /**
     * Resolve a tentativa de idempotência persistida para a operação
     * lógica (`tenant_id` + `operation`) ANTES de qualquer chamada HTTP —
     * é essa durabilidade (transação própria e curta, fora de qualquer
     * transação de negócio do caller) que impede um retry de gerar uma
     * chave nova quando a transação do caller reverte por causa de um
     * timeout/erro do PSP. Bloqueia (sem tocar o Mercado Pago) quando já
     * existe uma tentativa em voo (`locked`) ou um timeout ambíguo ainda
     * não reconciliado (`ambiguous`).
     */
    private function acquireIdempotency(int $tenantId, string $operation): IdempotencyOutcome
    {
        $outcome = $this->idempotencyRepository->findOrCreatePending($tenantId, $operation);

        if ($outcome->isLocked()) {
            throw new PaymentOperationInProgressException('payment.idempotency_locked');
        }

        if ($outcome->isAmbiguous()) {
            throw new PaymentOperationInProgressException('payment.idempotency_ambiguous');
        }

        if ($outcome->isAlreadySucceeded()) {
            // Estado raro (só ocorreria se a mesma chave lógica fosse
            // reaproveitada) — bloqueio defensivo, nunca chama o PSP de
            // novo por uma operação já concluída com sucesso.
            throw new PaymentOperationInProgressException('payment.idempotency_locked');
        }

        return $outcome;
    }

    /**
     * Executa a chamada mutante ao Mercado Pago sob o guard de
     * idempotência: erro de rede/timeout ou status ambíguo (408/429/5xx)
     * NUNCA marca a tentativa como `failed` — o Mercado Pago pode ter
     * processado do lado dele mesmo sem a resposta chegar aqui. Só um erro
     * de dado/validação claro (4xx "comum") é decisivo e libera uma nova
     * tentativa imediata com uma chave nova.
     */
    private function callWithIdempotencyGuard(IdempotencyOutcome $idempotency, \Closure $request, string $operationName): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            ApplicationLogger::error('Falha de rede/timeout ao chamar o Mercado Pago', [
                'operation' => $operationName,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            throw new PaymentProviderException('mercadopago.request_failed');
        }

        if ($response->successful()) {
            return $response;
        }

        $responseBody = $response->json();
        $providerMessage = (string) ($response->json('message') ?? $response->json('error') ?? '');

        ApplicationLogger::error('Falha na chamada ao Mercado Pago', [
            'operation' => $operationName,
            'status' => $response->status(),
            'mp_error' => $providerMessage !== '' ? $providerMessage : null,
            'mp_response' => is_array($responseBody) ? $this->sanitizeForLog($responseBody) : null,
        ]);

        if ($this->isAmbiguousStatus($response->status())) {
            // Timeout de gateway/instabilidade do PSP — pode ter sido
            // processado do lado dele mesmo com essa resposta. A
            // tentativa fica pending (mesmo raciocínio do
            // ConnectionException acima).
            throw new PaymentProviderException('mercadopago.request_failed');
        }

        // Erro de dado/validação claro (4xx "comum") — falha DECISIVA.
        $this->idempotencyRepository->markFailed($idempotency->record);

        if (
            $response->status() === 400
            && str_contains(strtolower($providerMessage), 'both payer and collector must be real or test users')
        ) {
            throw new PaymentProviderException('mercadopago.payer_collector_mismatch');
        }

        throw new PaymentProviderException('mercadopago.request_failed');
    }

    private function isAmbiguousStatus(int $status): bool
    {
        return $status >= 500 || in_array($status, [408, 429], true);
    }

    private function baseClient(?string $idempotencyKey = null): PendingRequest
    {
        $accessToken = (string) config('services.mercadopago.access_token');

        $headers = ['Content-Type' => 'application/json'];

        if ($idempotencyKey !== null) {
            $headers['X-Idempotency-Key'] = $idempotencyKey;
        }

        return Http::withToken($accessToken)
            ->withHeaders($headers)
            ->baseUrl(self::BASE_URL)
            ->timeout(15);
    }

    private function mutatingClient(?string $idempotencyKey = null): PendingRequest
    {
        return $this->baseClient($idempotencyKey);
    }

    private function queryClient(): PendingRequest
    {
        return $this->baseClient()->retry(1, 250, throw: false);
    }

    private function assertSuccessful(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $responseBody = $response->json();
        $providerMessage = (string) ($response->json('message') ?? $response->json('error') ?? '');

        // Nunca logar o body completo (pode conter dado do pagador) nem o
        // access_token (não está no body, mas por garantia não é incluído
        // aqui de forma alguma).
        ApplicationLogger::error('Falha na chamada ao Mercado Pago', [
            'operation' => $operation,
            'status' => $response->status(),
            'mp_error' => $providerMessage !== '' ? $providerMessage : null,
            'mp_response' => is_array($responseBody) ? $this->sanitizeForLog($responseBody) : null,
        ]);

        if (
            $response->status() === 400
            && str_contains(strtolower($providerMessage), 'both payer and collector must be real or test users')
        ) {
            throw new PaymentProviderException('mercadopago.payer_collector_mismatch');
        }

        throw new PaymentProviderException('mercadopago.request_failed');
    }

    /**
     * Remove campos sensíveis antes de registrar o retorno do PSP.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeForLog(array $payload): array
    {
        $sensitiveKeys = [
            'access_token',
            'authorization',
            'token',
            'card_token_id',
            'card_number',
            'security_code',
            'cvv',
            'payer_email',
        ];

        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $sanitized[$key] = '***';
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeForLog($value)
                : $value;
        }

        return $sanitized;
    }
}
