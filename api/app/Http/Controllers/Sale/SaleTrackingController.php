<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sale\SalePublicTrackingResource;
use App\Models\Sale\Sale;
use App\Services\APIResponse;

/**
 * Endpoint público (Fase 5.1 do roadmap), sem jwt/tenant/perm — protegido
 * só pelo uuid da venda ser imprevisível (uuid v4, 122 bits), mesmo
 * padrão de qualquer link de rastreio de transportadora. Route-model-
 * binding (HasUuid::resolveRouteBinding) já resolve por uuid e já cai em
 * 404 automático pra uuid inexistente ou venda soft-deletada — nenhum
 * guard extra necessário aqui. Sem Service/Repository: leitura simples de
 * um Model já resolvido pelo binding, sem filtro/paginação.
 */
class SaleTrackingController extends Controller
{
    public function show(Sale $sale)
    {
        $sale->load(['finalCustomer', 'tenant', 'items.ticketType', 'items.eventProduct', 'items.seat', 'installments', 'coupon']);

        return APIResponse::success(
            new SalePublicTrackingResource($sale),
            __('messages.order.tracking_shown')
        );
    }
}
