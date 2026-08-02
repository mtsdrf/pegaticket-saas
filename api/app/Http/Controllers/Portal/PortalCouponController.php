<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Storefront\CouponRedemption;
use App\Services\APIResponse;

/**
 * "Meus vouchers" = histórico de cupons já usados pelo cliente final
 * (roadmap Loja) — read-only, cross-tenant (mesmo espírito de
 * PortalController::sales()). Não é um sistema de voucher pessoal, só o
 * log de CouponRedemption do próprio cliente autenticado.
 */
class PortalCouponController extends Controller
{
    public function index()
    {
        $redemptions = CouponRedemption::where('final_customer_id', portal_customer()->id)
            ->with(['coupon', 'sale.tenant'])
            ->orderByDesc('redeemed_at')
            ->get();

        $data = $redemptions->map(fn($redemption) => [
            'coupon_code' => $redemption->coupon?->code,
            'tenant_name' => $redemption->sale?->tenant?->name,
            'redeemed_at' => $redemption->redeemed_at,
            'sale_uuid' => $redemption->sale?->uuid,
        ])->values();

        return APIResponse::success($data, __('messages.portal.coupon_redemptions_listed'));
    }
}
