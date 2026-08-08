<?php

namespace App\Services\Payment;

use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Exceptions\Payment\PagBankConnectException;
use App\Models\Tenant\TenantPagBankConnection;
use App\Repositories\Contracts\TenantPagBankConnectionRepositoryInterface;
use App\Support\PagBank\PagBankEnvironment;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fluxo OAuth Connect do PagBank (roadmap fase R2 —
 * docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md, seção
 * 2.1/2.2). Autoriza o tenant a conectar sua própria conta PagBank como
 * seller do split (ver PagBankPaymentProvider::buildSplitPayload), em vez
 * do fluxo manual antigo de colar `pagbank_receiver_account_id` direto em
 * tenant_settings (que continua existindo em paralelo).
 *
 * Endpoints confirmados na documentação oficial developer.pagbank.com.br
 * (2026-08-07):
 * - Autorização: GET connect(.sandbox).pagbank.com.br/oauth2/authorize
 * - Troca code->token: POST (sandbox.)api.pagseguro.com/oauth2/token
 * - Renovação: POST (sandbox.)api.pagseguro.com/oauth2/refresh — CONFIRMADO
 *   via developer.pagbank.com.br/reference/renovar-access-token.
 * - Revogação: POST (sandbox.)api.pagseguro.com/oauth2/revoke — CONFIRMADO
 *   via developer.pagbank.com.br/reference/revogar-access-token.
 *
 * Nunca logar access_token/refresh_token/code/client_secret — nem em
 * Log::, nem em mensagem de exceção (PagBankConnectException carrega só
 * um código curto, nunca o payload).
 */
class PagBankConnectService
{
    private const TOKEN_EXPIRY_SAFETY_MARGIN_MINUTES = 5;

    public function __construct(
        private TenantPagBankConnectionRepositoryInterface $repository,
        private PagBankTransactionLogger $metrics
    ) {}

    public function statusForTenant(int $tenantId): ?TenantPagBankConnection
    {
        return $this->repository->findForTenant($tenantId);
    }

    public function buildAuthorizationUrl(int $tenantId): string
    {
        $environment = $this->resolveEnvironment();
        $state = Str::random(64);

        DB::transaction(function () use ($tenantId, $state, $environment) {
            $connection = $this->firstOrNewForTenant($tenantId);
            $fromStatus = (string) ($connection->status ?? TenantPagBankConnection::STATUS_NOT_CONFIGURED);

            $connection->fill([
                'tenant_id' => $tenantId,
                'provider' => 'pagbank',
                'status' => TenantPagBankConnection::STATUS_PENDING_CONNECTION,
                'connect_state' => $state,
                'environment' => $environment,
            ]);
            $connection->save();

            $this->dispatchStatusChanged($tenantId, $fromStatus, TenantPagBankConnection::STATUS_PENDING_CONNECTION);
        });

        $params = [
            'response_type' => 'code',
            'client_id' => (string) config('services.pagbank.connect_client_id'),
            'redirect_uri' => (string) config('services.pagbank.connect_redirect_uri'),
            'scope' => 'payments.read+payments.create+payments.refund+accounts.read',
            'state' => $state,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        // scope já vem com '+' literal entre escopos (múltiplos valores),
        // não o '+' de espaço do RFC1738 — http_build_query com
        // PHP_QUERY_RFC3986 não escapa '+', então preservamos o literal.
        $baseUrl = PagBankEnvironment::resolveConnectAuthorizeUrl($environment);

        $this->metrics->metric('pagbank_receiving_setup_started_total', ['tenant_id' => $tenantId, 'path' => 'connect']);
        $this->metrics->metric('pagbank_connect_started_total', ['tenant_id' => $tenantId]);

        return $baseUrl.'?'.$query;
    }

    public function handleCallback(string $code, string $state): TenantPagBankConnection
    {
        $connection = $this->repository->findPendingByState($state);

        if ($connection === null) {
            $this->metrics->metric('pagbank_connect_failed_total', ['reason' => 'invalid_state']);

            throw new PagBankConnectException('pagbank_connect.invalid_state');
        }

        $environment = (string) $connection->environment;
        $response = $this->client($environment)->post('/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string) config('services.pagbank.connect_redirect_uri'),
        ]);

        if ($response->failed()) {
            $this->metrics->metric('pagbank_connect_failed_total', ['tenant_id' => $connection->tenant_id, 'reason' => 'token_exchange_failed']);

            throw new PagBankConnectException('pagbank_connect.token_exchange_failed');
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $refreshToken = (string) ($payload['refresh_token'] ?? '');
        $accountId = (string) ($payload['account_id'] ?? '');

        if ($accessToken === '' || $refreshToken === '' || $accountId === '') {
            $this->metrics->metric('pagbank_connect_failed_total', ['tenant_id' => $connection->tenant_id, 'reason' => 'token_exchange_failed']);

            throw new PagBankConnectException('pagbank_connect.token_exchange_failed');
        }

        return DB::transaction(function () use ($connection, $payload, $accessToken, $refreshToken, $accountId) {
            $fromStatus = (string) $connection->status;

            $connection->fill([
                'status' => TenantPagBankConnection::STATUS_ENABLED,
                'account_id' => $accountId,
                'access_token_encrypted' => $accessToken,
                'refresh_token_encrypted' => $refreshToken,
                'token_expires_at' => $this->resolveExpiresAt($payload['expires_in'] ?? null),
                'scope' => (string) ($payload['scope'] ?? ''),
                'connect_state' => null,
                'connected_at' => now(),
                'verified_at' => now(),
            ]);
            $connection->save();

            $this->dispatchStatusChanged((int) $connection->tenant_id, $fromStatus, TenantPagBankConnection::STATUS_ENABLED);
            $this->metrics->metric('pagbank_connect_success_total', ['tenant_id' => $connection->tenant_id]);

            return $connection->refresh();
        });
    }

    public function refreshTokenIfNeeded(TenantPagBankConnection $connection): TenantPagBankConnection
    {
        if (! $this->needsRefresh($connection)) {
            return $connection;
        }

        $refreshToken = (string) $connection->refresh_token_encrypted;

        if ($refreshToken === '') {
            throw new PagBankConnectException('pagbank_connect.refresh_failed');
        }

        $response = $this->client((string) $connection->environment)
            ->post('/oauth2/refresh', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new PagBankConnectException('pagbank_connect.refresh_failed');
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $newRefreshToken = (string) ($payload['refresh_token'] ?? '');

        if ($accessToken === '' || $newRefreshToken === '') {
            throw new PagBankConnectException('pagbank_connect.refresh_failed');
        }

        $connection->fill([
            'access_token_encrypted' => $accessToken,
            // Refresh token é rotativo: o anterior é invalidado pelo
            // PagBank assim que este é emitido, nunca reaproveitar.
            'refresh_token_encrypted' => $newRefreshToken,
            'token_expires_at' => $this->resolveExpiresAt($payload['expires_in'] ?? null),
        ]);
        $connection->save();

        return $connection;
    }

    public function disconnect(TenantPagBankConnection $connection): void
    {
        $accessToken = (string) $connection->access_token_encrypted;

        if ($accessToken !== '') {
            // Falha ao revogar no PagBank não deve travar a desconexão local
            // (o tenant quer parar de usar essa conta agora) — registra e
            // segue. TODO: alertar operação se revogação remota falhar
            // repetidamente (indício de token já inválido do lado PagBank).
            try {
                $this->client((string) $connection->environment)
                    ->post('/oauth2/revoke', [
                        'token_type_hint' => 'access_token',
                        'token' => $accessToken,
                    ]);
            } catch (\Throwable) {
                // Intencionalmente silencioso — ver comentário acima.
            }
        }

        DB::transaction(function () use ($connection) {
            $fromStatus = (string) $connection->status;

            $connection->fill([
                'status' => TenantPagBankConnection::STATUS_DISABLED,
                'access_token_encrypted' => null,
                'refresh_token_encrypted' => null,
                'token_expires_at' => null,
                'connect_state' => null,
                'disconnected_at' => now(),
            ]);
            $connection->save();

            $this->dispatchStatusChanged((int) $connection->tenant_id, $fromStatus, TenantPagBankConnection::STATUS_DISABLED);
        });
    }

    private function needsRefresh(TenantPagBankConnection $connection): bool
    {
        if ($connection->token_expires_at === null) {
            return false;
        }

        return Carbon::now()->addMinutes(self::TOKEN_EXPIRY_SAFETY_MARGIN_MINUTES)->greaterThanOrEqualTo($connection->token_expires_at);
    }

    private function resolveExpiresAt(mixed $expiresIn): ?CarbonInterface
    {
        if ($expiresIn === null || ! is_numeric($expiresIn)) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }

    private function firstOrNewForTenant(int $tenantId): TenantPagBankConnection
    {
        return $this->repository->findForTenant($tenantId) ?? new TenantPagBankConnection;
    }

    private function dispatchStatusChanged(int $tenantId, string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        event(new TenantPagBankConnectionStatusChanged(
            tenantId: $tenantId,
            actorId: (int) (Auth::id() ?? 0),
            fromStatus: $fromStatus,
            toStatus: $toStatus
        ));
    }

    private function resolveEnvironment(): string
    {
        return (string) config('services.pagbank.environment', 'sandbox');
    }

    /**
     * @return array<string, string>
     */
    private function applicationAuthHeaders(): array
    {
        return [
            'X_CLIENT_ID' => (string) config('services.pagbank.connect_client_id'),
            'X_CLIENT_SECRET' => (string) config('services.pagbank.connect_client_secret'),
        ];
    }

    /**
     * Client HTTP para /oauth2/token, /oauth2/refresh e /oauth2/revoke —
     * os três exigem, além de X_CLIENT_ID/X_CLIENT_SECRET, um
     * `Authorization: Bearer <token da aplicação>`. Reaproveita
     * `services.pagbank.token` (mesmo PAGBANK_TOKEN_SANDBOX/PROD já usado
     * por PagBankPaymentProvider para as chamadas da PLATAFORMA) como esse
     * "token da aplicação" — a doc oficial não nomeia explicitamente qual
     * token é esse além de "token da aplicação"; TODO: confirmar com o
     * suporte PagBank/homologação se é de fato o mesmo token de seller da
     * plataforma ou um token de aplicação Connect à parte antes de produção.
     */
    private function client(string $environment): PendingRequest
    {
        $baseUrl = PagBankEnvironment::resolveApiBaseUrl($environment);

        return Http::withToken((string) config('services.pagbank.token'))
            ->withHeaders($this->applicationAuthHeaders())
            ->baseUrl($baseUrl)
            ->timeout(15);
    }
}
