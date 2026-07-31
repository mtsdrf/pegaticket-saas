<?php

namespace App\Http\Controllers\Accounting;

use App\DTOs\Accounting\ApproveAccessDTO;
use App\Exceptions\AccountingAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ApproveAccessRequest;
use App\Http\Resources\Accounting\AccountingOfficeTenantResource;
use App\Services\Accounting\AccountingAccessApprovalService;
use App\Services\APIResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Lado do TENANT: dono da empresa lista, aprova (com escopos) e revoga
 * solicitações de acesso do contador. Tenant-scoped (`tenant` +
 * `perm:accounting-access,{action}`).
 */
class AccountingAccessApprovalController extends Controller
{
    public function __construct(
        private AccountingAccessApprovalService $service
    ) {
    }

    public function index()
    {
        return APIResponse::success(
            AccountingOfficeTenantResource::collection($this->service->listForTenant(app('tenant_id'))),
            __('messages.accounting_access.list')
        );
    }

    public function approve(ApproveAccessRequest $request, string $uuid)
    {
        try {
            $link = $this->service->approve(
                $uuid,
                app('tenant_id'),
                ApproveAccessDTO::fromArray($request->validated()),
                Auth::id()
            );
        } catch (AccountingAccessException $e) {
            return APIResponse::error($e->getMessage(), 422, 'ACCOUNTING_ACCESS_ERROR');
        }

        return APIResponse::success(
            new AccountingOfficeTenantResource($link),
            __('messages.accounting_access.approved')
        );
    }

    public function revoke(string $uuid)
    {
        $link = $this->service->revoke($uuid, app('tenant_id'), Auth::id());

        return APIResponse::success(
            new AccountingOfficeTenantResource($link),
            __('messages.accounting_access.revoked')
        );
    }
}
