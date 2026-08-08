<?php

namespace App\Http\Controllers\Payment;

use App\DTOs\Payment\CreatePagBankSellerAccountDTO;
use App\DTOs\Payment\PagBankConnectCallbackDTO;
use App\Exceptions\Payment\PagBankAccountException;
use App\Exceptions\Payment\PagBankConnectException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePagBankSellerAccountRequest;
use App\Http\Requests\Payment\PagBankConnectCallbackRequest;
use App\Http\Resources\Payment\TenantPagBankConnectionResource;
use App\Services\APIResponse;
use App\Services\Payment\PagBankAccountService;
use App\Services\Payment\PagBankConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Concentra os dois caminhos do subdomínio de onboarding financeiro
 * (roadmap seção 9.8): Connect (R2.1, OAuth com conta já existente) e
 * Account/Cadastro (R2.2, criação de conta SELLER nova). Mantido no
 * mesmo controller — não criado `PagBankAccountController` separado —
 * porque os dois caminhos operam sobre o mesmo recurso
 * (TenantPagBankConnection, um registro por tenant) e a mesma permissão
 * (`tenant_settings,update`); controller continua fino, cada método só
 * orquestra DTO->Service->Resource. Reavaliar se o subdomínio crescer
 * muito mais (ex.: quando a permissão dedicada `financial_receiving` da
 * seção 9.5.9 for criada).
 */
class PagBankConnectController extends Controller
{
    public function __construct(
        private PagBankConnectService $service,
        private PagBankAccountService $accountService
    ) {}

    public function authorizeUrl()
    {
        $url = $this->service->buildAuthorizationUrl(app('tenant_id'));

        return APIResponse::success(
            ['authorize_url' => $url],
            __('messages.pagbank_connect.authorize_url_generated')
        );
    }

    public function status()
    {
        $connection = $this->service->statusForTenant(app('tenant_id'));

        return APIResponse::success(
            $connection ? new TenantPagBankConnectionResource($connection) : null,
            __('messages.pagbank_connect.status_shown')
        );
    }

    public function disconnect()
    {
        $connection = $this->service->statusForTenant(app('tenant_id'));

        if ($connection === null) {
            return APIResponse::error(
                __('messages.pagbank_connect.not_connected'),
                422,
                'PAGBANK_CONNECT_NOT_CONNECTED'
            );
        }

        $this->service->disconnect($connection);

        return APIResponse::success(null, __('messages.pagbank_connect.disconnected'));
    }

    public function createAccount(CreatePagBankSellerAccountRequest $request): JsonResponse
    {
        $dto = CreatePagBankSellerAccountDTO::fromArray($request->validated());

        try {
            $connection = $this->accountService->createSellerAccount(app('tenant_id'), $dto, (string) $request->ip());
        } catch (PagBankAccountException $e) {
            $code = $e->getMessage() === 'pagbank_account.already_submitted'
                ? 'PAGBANK_ACCOUNT_ALREADY_SUBMITTED'
                : 'PAGBANK_ACCOUNT_CREATION_FAILED';

            return APIResponse::error($e->userMessage(), 422, $code);
        }

        return APIResponse::success(
            new TenantPagBankConnectionResource($connection),
            __('messages.pagbank_account.created')
        );
    }

    /**
     * Rota pública (fora de jwt/tenant/perm) — o navegador do usuário é
     * redirecionado aqui diretamente pelo PagBank, sem sessão/token da
     * API. Nunca retorna JSON: sempre redireciona de volta pro frontend
     * com o resultado em query string, sucesso ou erro.
     */
    public function callback(PagBankConnectCallbackRequest $request): RedirectResponse
    {
        $dto = PagBankConnectCallbackDTO::fromArray($request->validated());
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/').'/configuracoes/pagbank-connect';

        try {
            $this->service->handleCallback($dto->code, $dto->state);
        } catch (PagBankConnectException $e) {
            return redirect()->away($frontendUrl.'?pagbank_connect=error&reason='.urlencode($e->getMessage()));
        }

        return redirect()->away($frontendUrl.'?pagbank_connect=success');
    }
}
