<?php

namespace App\Http\Controllers\ApiKey;

use App\DTOs\ApiKey\CreateApiKeyDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiKey\StoreApiKeyRequest;
use App\Http\Resources\ApiKey\ApiKeyResource;
use App\Models\ApiKey\TenantApiKey;
use App\Services\ApiKey\ApiKeyService;
use App\Services\APIResponse;

class ApiKeyController extends Controller
{
    public function __construct(private ApiKeyService $service)
    {
    }

    public function index()
    {
        $apiKeys = $this->service->listForTenant(app('tenant_id'));

        return APIResponse::success(
            ApiKeyResource::collection($apiKeys),
            __('messages.api_key.list')
        );
    }

    /**
     * Única resposta que carrega a chave em texto puro (`key`) — nunca mais
     * recuperável depois. Ver ApiKeyService::create.
     */
    public function store(StoreApiKeyRequest $request)
    {
        $dto = CreateApiKeyDTO::fromArray($request->validated());

        $result = $this->service->create(app('tenant_id'), $dto);

        return APIResponse::success(
            array_merge(
                (new ApiKeyResource($result['model']))->resolve(),
                ['key' => $result['plainKey']]
            ),
            __('messages.api_key.created'),
            201
        );
    }

    public function destroy(TenantApiKey $apiKey)
    {
        $this->service->revoke($apiKey, app('tenant_id'));

        return APIResponse::success(
            null,
            __('messages.api_key.revoked'),
            204
        );
    }
}
