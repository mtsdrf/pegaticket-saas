<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\Payment\PaymentIssueNotFoundException;
use App\Exceptions\Payment\PaymentIssueNotReprocessableException;
use App\Exceptions\Payment\PaymentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ListPaymentIssuesRequest;
use App\Http\Requests\Payment\ReprocessPaymentIssueRequest;
use App\Http\Resources\Payment\PaymentIssueResource;
use App\Services\APIResponse;
use App\Services\Payment\PaymentIssueService;

/**
 * Visão cross-tenant EXCLUSIVA do staff interno da Maskats (`perm:
 * payment_admin,*`, sem middleware `tenant` — mesmo padrão de `tenants`/
 * `plans`/`audit-logs`: perm resolvida só via `group_permissions`,
 * `tenant_role_permissions` nunca entra porque não há tenant resolvido
 * nesta rota). Não é a tela de conciliação do PRÓPRIO tenant (essa é
 * `GET /finance/reconciliation`, tenant-scoped).
 */
class PaymentIssueController extends Controller
{
    public function __construct(
        private PaymentIssueService $service
    ) {
    }

    public function index(ListPaymentIssuesRequest $request)
    {
        $validated = $request->validated();

        $list = $this->service->list(
            collect($validated)->only(['type'])->all(),
            (int) ($validated['per_page'] ?? 15),
            (int) ($validated['page'] ?? 1)
        );

        return APIResponse::success(
            PaymentIssueResource::collection($list->items()),
            __('messages.payment_admin.issues_listed'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ]
            ]
        );
    }

    public function reprocess(ReprocessPaymentIssueRequest $request, string $reference)
    {
        $validated = $request->validated();

        try {
            $result = $this->service->reprocess($validated['type'], $reference);
        } catch (PaymentIssueNotFoundException $e) {
            return APIResponse::error(__('messages.payment_admin.issue_not_found'), 404, 'PAYMENT_ISSUE_NOT_FOUND');
        } catch (PaymentIssueNotReprocessableException $e) {
            return APIResponse::error(
                __('messages.payment_admin.issue_not_reprocessable'),
                422,
                'PAYMENT_ISSUE_NOT_REPROCESSABLE'
            );
        } catch (PaymentProviderException $e) {
            return APIResponse::error($e->userMessage(), 502, 'PAYMENT_PROVIDER_UNAVAILABLE');
        }

        return APIResponse::success($result, __('messages.payment_admin.issue_reprocessed'));
    }
}
