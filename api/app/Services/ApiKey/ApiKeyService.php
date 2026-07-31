<?php

namespace App\Services\ApiKey;

use App\DTOs\ApiKey\CreateApiKeyDTO;
use App\Events\ApiKey\ApiKeyCreated;
use App\Events\ApiKey\ApiKeyRevoked;
use App\Models\ApiKey\TenantApiKey;
use App\Repositories\Contracts\TenantApiKeyRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * API pública + webhooks de saída (roadmap A6, item 20). A chave em texto
 * puro (`mk_live_...`) só existe na memória durante create() — só o hash
 * (`hash('sha512', ...)`, mesmo padrão de `password_reset_token_hash`/
 * `pending_email_token_hash`, não Hash::make/bcrypt: aqui precisamos
 * localizar a linha pelo hash em cada requisição pública, não comparar
 * candidato a candidato) é persistido. Nunca é possível recuperar o valor
 * depois — igual a qualquer secret gerado do projeto.
 */
class ApiKeyService
{
    private const KEY_PREFIX = 'mk_live_';

    public function __construct(
        private TenantApiKeyRepositoryInterface $repository,
    ) {
    }

    public function listForTenant(int $tenantId): Collection
    {
        return $this->repository->listForTenant($tenantId);
    }

    /**
     * @return array{model: TenantApiKey, plainKey: string}
     */
    public function create(int $tenantId, CreateApiKeyDTO $dto): array
    {
        $plainKey = self::KEY_PREFIX . Str::random(48);

        $apiKey = $this->repository->create([
            'tenant_id' => $tenantId,
            'name' => $dto->name,
            'key_hash' => $this->hash($plainKey),
        ]);

        event(new ApiKeyCreated(
            apiKeyUuid: $apiKey->uuid,
            tenantId: $tenantId,
            actorId: (int) Auth::id()
        ));

        return ['model' => $apiKey, 'plainKey' => $plainKey];
    }

    public function revoke(TenantApiKey $apiKey, int $tenantId): TenantApiKey
    {
        $this->assertBelongsToCurrentTenant($apiKey, $tenantId);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        event(new ApiKeyRevoked(
            apiKeyUuid: $apiKey->uuid,
            tenantId: $tenantId,
            actorId: (int) Auth::id()
        ));

        return $apiKey->fresh();
    }

    public function hash(string $plainKey): string
    {
        return hash('sha512', $plainKey);
    }

    public function resolveActive(string $plainKey): ?TenantApiKey
    {
        return $this->repository->findActiveByHash($this->hash($plainKey));
    }

    /**
     * Mesmo padrão de OrderService::assertBelongsToCurrentTenant — 404, não
     * 403, pra não revelar a outro tenant que o uuid existe.
     */
    private function assertBelongsToCurrentTenant(TenantApiKey $apiKey, int $tenantId): void
    {
        if ((int) $apiKey->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
