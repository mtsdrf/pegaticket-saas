<?php

namespace App\Http\Middleware;

use App\Services\ApiKey\ApiKeyService;
use App\Services\APIResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * API pública + webhooks de saída (roadmap A6, item 20). Autentica
 * requisições de `/api/v1/public/*` via `Authorization: Bearer mk_live_...`
 * — substitui `tenant` (ResolveTenant) para esse contexto: não há JWT/
 * usuário staff aqui, a própria chave já identifica e autoriza o tenant
 * (sem `perm:`, a posse da chave é a autorização). Popula os mesmos
 * bindings (`tenant`/`tenant_id`/`tenant_uuid`) pra que Services/Resources
 * internos (OrderService, ProductService, tenant()) funcionem sem alteração.
 */
class ApiKeyAccess
{
    public function __construct(private ApiKeyService $apiKeyService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        $plainKey = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;

        if (!$plainKey) {
            return APIResponse::error(__('messages.api_key.missing'), 401, 'API_KEY_MISSING');
        }

        $apiKey = $this->apiKeyService->resolveActive($plainKey);

        if (!$apiKey || !$apiKey->tenant || !$apiKey->tenant->is_active) {
            return APIResponse::error(__('messages.api_key.invalid'), 401, 'API_KEY_INVALID');
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $tenant = $apiKey->tenant;

        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
        app()->instance('tenant_uuid', $tenant->uuid);
        app()->instance('tenant_api_key', $apiKey);

        return $next($request);
    }
}
