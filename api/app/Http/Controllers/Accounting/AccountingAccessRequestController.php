<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Accounting\CreateAccessRequestDTO;
use App\Exceptions\AccountingAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateAccessRequestRequest;
use App\Http\Resources\Accounting\AccountingOfficeTenantResource;
use App\Services\Accounting\AccountingAccessRequestService;
use App\Services\APIResponse;

/**
 * Lado do CONTADOR: solicita acesso a um tenant (por CNPJ) e lista seus
 * vínculos. Autenticado via `accounting.jwt` (identidade AccountingOffice) —
 * nunca `jwt`/`perm` (o contador não pertence a nenhum tenant).
 */
class AccountingAccessRequestController extends Controller
{
    public function __construct(
        private AccountingAccessRequestService $service
    ) {
    }

    public function store(CreateAccessRequestRequest $request)
    {
        try {
            $link = $this->service->request(
                accounting_office(),
                CreateAccessRequestDTO::fromArray($request->validated())
            );
        } catch (AccountingAccessException $e) {
            return APIResponse::error($e->getMessage(), 422, 'ACCOUNTING_ACCESS_ERROR');
        }

        return APIResponse::success(
            new AccountingOfficeTenantResource($link),
            __('messages.accounting_access.requested'),
            201
        );
    }

    public function index()
    {
        return APIResponse::success(
            AccountingOfficeTenantResource::collection($this->service->myLinks(accounting_office())),
            __('messages.accounting_access.my_links')
        );
    }
}
