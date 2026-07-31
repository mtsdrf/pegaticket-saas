<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Accounting\CreateAccountingMessageDTO;
use App\Exceptions\AccountingAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateAccountingMessageRequest;
use App\Http\Resources\Accounting\AccountingMessageResource;
use App\Services\Accounting\AccountingMessageService;
use App\Services\APIResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Lado do TENANT da central de pendências. O vínculo é resolvido por uuid,
 * escopado ao tenant ativo (guard de posse). Tenant-scoped
 * (`tenant` + `perm:accounting-access,{read|create}`).
 */
class TenantAccountingMessageController extends Controller
{
    public function __construct(
        private AccountingMessageService $service
    ) {
    }

    public function index(string $uuid)
    {
        try {
            $link = $this->service->resolveApprovedTenantLink($uuid, app('tenant_id'));
        } catch (AccountingAccessException $e) {
            return APIResponse::error($e->getMessage(), 422, 'ACCOUNTING_ACCESS_ERROR');
        }

        return APIResponse::success(
            AccountingMessageResource::collection($this->service->listForLink($link)),
            __('messages.accounting_message.list')
        );
    }

    public function store(CreateAccountingMessageRequest $request, string $uuid)
    {
        try {
            $link = $this->service->resolveApprovedTenantLink($uuid, app('tenant_id'));
        } catch (AccountingAccessException $e) {
            return APIResponse::error($e->getMessage(), 422, 'ACCOUNTING_ACCESS_ERROR');
        }

        $message = $this->service->create(
            $link,
            CreateAccountingMessageDTO::fromArray($request->validated()),
            AccountingMessageService::SENDER_TENANT,
            Auth::id(),
            $request->file('attachment')
        );

        return APIResponse::success(
            new AccountingMessageResource($message),
            __('messages.accounting_message.sent'),
            201
        );
    }
}
