<?php

namespace App\Http\Resources\ApiKey;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nunca expõe key_hash. O texto puro da chave só existe na resposta do
 * POST /api-keys (ver ApiKeyController::store, campo `key` fora deste
 * Resource) — não é responsabilidade deste Resource, que representa o
 * registro persistido de forma segura em qualquer outra listagem/rota.
 */
class ApiKeyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'last_used_at' => optional($this->last_used_at)->toIso8601String(),
            'revoked_at' => optional($this->revoked_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
