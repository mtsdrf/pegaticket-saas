<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Tenant;
use App\Repositories\Contracts\FinalCustomerTenantLinkRepositoryInterface;
use App\Services\APIResponse;
use App\Services\Storefront\CashbackService;
use Illuminate\Http\Request;

/**
 * Saldo de cashback do cliente final (roadmap Delivery, Fase 5) — reusado
 * tanto pelo extrato do Portal quanto pelo checkout da loja (mesmo guard
 * customer.jwt nos dois). Sem `?tenant_slug`, devolve o saldo de TODAS as
 * lojas com vínculo confirmado (mesmo espírito cross-tenant de
 * PortalController::orders()); com `?tenant_slug`, devolve só o saldo
 * daquela loja (uso do checkout, que já sabe seu próprio slug).
 */
class PortalCashbackController extends Controller
{
    public function __construct(
        private CashbackService $cashbackService,
        private FinalCustomerTenantLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function index(Request $request)
    {
        $customer = portal_customer();
        $slug = $request->query('tenant_slug');

        if ($slug) {
            $tenant = Tenant::where('slug', $slug)->whereNull('deleted_at')->firstOrFail();

            $balance = $this->cashbackService->getBalance((int) $tenant->id, (int) $customer->id);

            return APIResponse::success(
                array_merge($balance, [
                    'tenant_uuid' => $tenant->uuid,
                    'tenant_name' => $tenant->name,
                    'tenant_slug' => $tenant->slug,
                ]),
                __('messages.cashback.balance_shown')
            );
        }

        $links = $this->linkRepository->confirmedLinksFor((int) $customer->id);

        $balances = $links->map(function ($link) use ($customer) {
            $balance = $this->cashbackService->getBalance((int) $link->tenant_id, (int) $customer->id);

            return array_merge($balance, [
                'tenant_uuid' => $link->tenant->uuid,
                'tenant_name' => $link->tenant->name,
                'tenant_slug' => $link->tenant->slug,
            ]);
        })->values();

        return APIResponse::success($balances, __('messages.cashback.balance_shown'));
    }
}
