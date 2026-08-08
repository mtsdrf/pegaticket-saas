<?php

namespace App\Services\Payment;

use App\DTOs\Payment\CreatePagBankSellerAccountDTO;
use App\Events\Payment\TenantPagBankConnectionStatusChanged;
use App\Exceptions\Payment\PagBankAccountException;
use App\Models\Tenant\TenantPagBankConnection;
use App\Repositories\Contracts\TenantPagBankConnectionRepositoryInterface;
use App\Support\PagBank\PagBankEnvironment;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Caminho Account/Cadastro do PagBank Connect (roadmap fase R2.2 —
 * docs/roadmap/2026-08-07-pagbank-homologacao-producao-roadmap.md, seção
 * 9.8/9.10). Cria uma conta SELLER nova (PF ou PJ) para tenants que ainda
 * não têm conta PagBank própria — complementar a PagBankConnectService
 * (que conecta uma conta já existente via OAuth). Reaproveita a mesma
 * tabela/model `TenantPagBankConnection` (connection_type=
 * created_by_platform diferencia o subconjunto de estados válido).
 *
 * Endpoints confirmados via developer.pagbank.com.br em 2026-08-08:
 * - Criar conta: POST (sandbox.)api.pagseguro.com/accounts — headers
 *   `x-client-id`/`x-client-secret` (minúsculo/hífen, DIFERENTE de
 *   X_CLIENT_ID/X_CLIENT_SECRET usados por PagBankConnectService no
 *   OAuth) + `Authorization: Bearer <token da aplicação>`.
 * - Consultar conta: GET (sandbox.)api.pagseguro.com/accounts/{id} —
 *   headers `x-client-token` (access_token do seller retornado na
 *   criação, não o client_secret da aplicação) + `Authorization: Bearer
 *   <token da aplicação>`.
 *
 * Nunca logar access_token/refresh_token/tax_id/CPF/CNPJ — nem em Log::,
 * nem em mensagem de exceção (PagBankAccountException carrega só um
 * código curto, nunca o payload), mesmo padrão de PagBankConnectService.
 */
class PagBankAccountService
{
    // Categoria de negócio mais próxima do domínio de venda de ingressos
    // no enum oficial do PagBank (nenhuma categoria específica de
    // eventos/ingressos existe hoje) — constante nomeada para facilitar
    // troca se o PagBank adicionar uma categoria mais específica.
    private const BUSINESS_CATEGORY = 'ENTERTAINMENT';

    public function __construct(
        private TenantPagBankConnectionRepositoryInterface $repository,
        private PagBankTransactionLogger $metrics
    ) {}

    public function createSellerAccount(int $tenantId, CreatePagBankSellerAccountDTO $dto, string $requestIp): TenantPagBankConnection
    {
        $environment = $this->resolveEnvironment();

        $this->metrics->metric('pagbank_receiving_setup_started_total', ['tenant_id' => $tenantId, 'path' => 'account']);

        // A chamada HTTP ao PagBank NUNCA pode ficar dentro da mesma
        // DB::transaction() que lança PagBankAccountException — uma
        // exceção dentro de DB::transaction() faz rollback de TUDO,
        // inclusive do registro de erro que gravaríamos logo antes de
        // lançar. Por isso: (1) transação curta só para lock +
        // idempotência + marcar pending_submission; (2) chamada HTTP
        // fora de transação; (3) resultado (sucesso ou erro) persistido
        // em uma segunda transação curta.
        $connection = DB::transaction(function () use ($tenantId, $dto, $environment) {
            /** @var TenantPagBankConnection|null $connection */
            $connection = TenantPagBankConnection::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($connection !== null && $connection->hasSubmittedAccountRequest()) {
                throw new PagBankAccountException('pagbank_account.already_submitted');
            }

            $connection ??= new TenantPagBankConnection;
            $fromStatus = (string) ($connection->status ?? TenantPagBankConnection::STATUS_NOT_CONFIGURED);

            $connection->fill([
                'tenant_id' => $tenantId,
                'provider' => 'pagbank',
                'connection_type' => TenantPagBankConnection::CONNECTION_TYPE_CREATED_BY_PLATFORM,
                'account_person_type' => $dto->personType,
                'status' => TenantPagBankConnection::STATUS_PENDING_SUBMISSION,
                'document_encrypted' => $dto->primaryDocument(),
                'environment' => $environment,
            ]);
            $connection->save();

            $this->dispatchStatusChanged($tenantId, $fromStatus, TenantPagBankConnection::STATUS_PENDING_SUBMISSION);

            return $connection;
        });

        $response = $this->client($environment)->post('/accounts', $this->buildPayload($dto, $requestIp));
        $payload = $response->json() ?? [];
        $accountId = (string) ($payload['id'] ?? '');
        $token = $payload['token'] ?? [];
        $accessToken = (string) ($token['access_token'] ?? '');

        if ($response->failed() || $accountId === '' || $accessToken === '') {
            DB::transaction(function () use ($connection) {
                $fromStatus = (string) $connection->status;
                $connection->fill(['status' => TenantPagBankConnection::STATUS_ERROR]);
                $connection->save();
                $this->dispatchStatusChanged((int) $connection->tenant_id, $fromStatus, TenantPagBankConnection::STATUS_ERROR);
            });

            $this->metrics->metric('pagbank_account_creation_failed_total', ['tenant_id' => $connection->tenant_id]);

            throw new PagBankAccountException('pagbank_account.creation_failed');
        }

        return DB::transaction(function () use ($connection, $accountId, $accessToken, $token, $payload) {
            $fromStatus = (string) $connection->status;

            // Sandbox aprova o KYC automaticamente segundo a doc oficial,
            // mas o status interno permanece "submitted" até uma
            // consulta explícita confirmar (syncStatus) — nunca assumir
            // aprovação em produção só pela resposta de criação.
            $connection->fill([
                'account_id' => $accountId,
                'access_token_encrypted' => $accessToken,
                'refresh_token_encrypted' => (string) ($token['refresh_token'] ?? ''),
                'token_expires_at' => $this->resolveExpiresAt($token['expires_in'] ?? null),
                'scope' => (string) ($token['scope'] ?? ''),
                'status' => TenantPagBankConnection::STATUS_SUBMITTED,
                'provider_status' => (string) ($payload['type'] ?? null),
                'submitted_at' => now(),
            ]);
            $connection->save();

            $this->dispatchStatusChanged((int) $connection->tenant_id, $fromStatus, TenantPagBankConnection::STATUS_SUBMITTED);
            $this->metrics->metric('pagbank_accounts_created_total', ['tenant_id' => $connection->tenant_id]);

            return $connection->refresh();
        });
    }

    /**
     * Consulta o status atual da conta no PagBank e atualiza
     * provider_status + status interno. Base reutilizável para o job de
     * sincronização periódica (R2.5) — aqui só é chamado de forma
     * síncrona, uma vez, logo após a criação.
     */
    public function syncStatus(TenantPagBankConnection $connection): TenantPagBankConnection
    {
        $accountId = (string) $connection->account_id;
        $accessToken = (string) $connection->access_token_encrypted;

        if ($accountId === '' || $accessToken === '') {
            throw new PagBankAccountException('pagbank_account.no_account');
        }

        $response = $this->client((string) $connection->environment)
            ->withHeaders(['x-client-token' => $accessToken])
            ->get('/accounts/'.$accountId);

        if ($response->failed()) {
            $this->metrics->metric('pagbank_account_status_sync_failed_total', ['tenant_id' => $connection->tenant_id]);

            throw new PagBankAccountException('pagbank_account.sync_failed');
        }

        $payload = $response->json();
        $providerStatus = (string) ($payload['status'] ?? '');

        return DB::transaction(function () use ($connection, $providerStatus) {
            $fromStatus = (string) $connection->status;
            $newStatus = $this->mapProviderStatus($providerStatus, $fromStatus);

            $connection->fill([
                'provider_status' => $providerStatus !== '' ? $providerStatus : $connection->provider_status,
                'status' => $newStatus,
                'verified_at' => $newStatus === TenantPagBankConnection::STATUS_VERIFIED ? now() : $connection->verified_at,
            ]);
            $connection->save();

            $this->dispatchStatusChanged((int) $connection->tenant_id, $fromStatus, $newStatus);

            return $connection->refresh();
        });
    }

    private function mapProviderStatus(string $providerStatus, string $currentStatus): string
    {
        // Mapeamento conservador: só os valores confirmados na doc
        // (developer.pagbank.com.br/reference/consultar-conta, exemplo
        // "ACTIVE") são traduzidos com confiança. TODO: confirmar o
        // catálogo completo de status possíveis (ex.: análise/recusa)
        // antes de produção — qualquer valor não mapeado mantém o
        // status interno atual em vez de arriscar uma transição errada.
        return match ($providerStatus) {
            'ACTIVE' => TenantPagBankConnection::STATUS_VERIFIED,
            'REJECTED', 'DENIED' => TenantPagBankConnection::STATUS_REJECTED,
            'PENDING', 'UNDER_REVIEW' => TenantPagBankConnection::STATUS_UNDER_REVIEW,
            default => $currentStatus,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(CreatePagBankSellerAccountDTO $dto, string $requestIp): array
    {
        $payload = [
            'type' => 'SELLER',
            'email' => $dto->email,
            'business_category' => self::BUSINESS_CATEGORY,
            'person' => [
                'name' => $dto->personName,
                'birth_date' => $dto->personBirthDate,
                'mother_name' => $dto->personMotherName,
                'tax_id' => $dto->personTaxId,
                'address' => $dto->personAddress,
                'phones' => [$dto->personPhone],
            ],
            'tos_acceptance' => [
                'user_ip' => $requestIp,
                'date' => $dto->termsAcceptedAt->format('Y-m-d\TH:i:s.vP'),
            ],
        ];

        if ($dto->personType === 'pj') {
            $payload['company'] = [
                'name' => $dto->companyName,
                'tax_id' => $dto->companyTaxId,
                'address' => $dto->companyAddress,
                'phones' => [$dto->companyPhone],
            ];
        }

        return $payload;
    }

    private function resolveExpiresAt(mixed $expiresIn): ?CarbonInterface
    {
        if ($expiresIn === null || ! is_numeric($expiresIn)) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
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
        // Mesmas credenciais de aplicação do OAuth Connect
        // (PAGBANK_CONNECT_CLIENT_ID/SECRET) — confirmado na doc oficial
        // que a API de Cadastro usa a mesma aplicação PegaTicket
        // cadastrada no PagBank, só com nomes de header diferentes
        // (x-client-id/x-client-secret em minúsculo, vs X_CLIENT_ID/
        // X_CLIENT_SECRET do OAuth). Nenhuma variável de ambiente nova
        // necessária.
        return [
            'x-client-id' => (string) config('services.pagbank.connect_client_id'),
            'x-client-secret' => (string) config('services.pagbank.connect_client_secret'),
        ];
    }

    private function client(string $environment): PendingRequest
    {
        $baseUrl = PagBankEnvironment::resolveApiBaseUrl($environment);

        return Http::withToken((string) config('services.pagbank.token'))
            ->withHeaders($this->applicationAuthHeaders())
            ->baseUrl($baseUrl)
            ->timeout(15);
    }
}
