<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderPublicTrackingResource;
use App\Models\Order\Order;
use App\Services\APIResponse;

/**
 * Endpoint público (Fase 5.1 do roadmap), sem jwt/tenant/perm — protegido
 * só pelo uuid do pedido ser imprevisível (uuid v4, 122 bits), mesmo
 * padrão de qualquer link de rastreio de transportadora. Route-model-
 * binding (HasUuid::resolveRouteBinding) já resolve por uuid e já cai em
 * 404 automático pra uuid inexistente ou pedido soft-deletado — nenhum
 * guard extra necessário aqui. Sem Service/Repository: leitura simples de
 * um Model já resolvido pelo binding, sem filtro/paginação.
 */
class OrderTrackingController extends Controller
{
    public function show(Order $order)
    {
        $order->load(['finalCustomer', 'tenant', 'items.ticketType', 'items.eventProduct', 'installments', 'coupon']);

        return APIResponse::success(
            new OrderPublicTrackingResource($order),
            __('messages.order.tracking_shown')
        );
    }
}
