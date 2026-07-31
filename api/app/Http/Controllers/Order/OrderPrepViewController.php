<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order\Order;
use App\Models\Order\OrderPrepLink;
use App\Services\APIResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;

/**
 * Leitura pública do pedido pra tela de preparo no celular (roadmap Loja) —
 * 100% pública, sem jwt/tenant/customer.jwt/perm, protegida só pelo token
 * temporário (OrderPrepLink) gerado pelo staff. Resolve o Order manualmente
 * por uuid (não route-model-binding scoped) e nunca revela se o pedido
 * existe: token errado, expirado, de outro pedido ou pedido inexistente
 * respondem todos o MESMO 404 genérico. Reaproveita OrderResource (mesmo
 * shape do endpoint autenticado), não duplica.
 */
class OrderPrepViewController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $token = (string) $request->query('token', '');

        $order = Order::where('uuid', $uuid)->whereNull('deleted_at')->first();

        if (!$order) {
            abort(404);
        }

        $link = OrderPrepLink::where('token', $token)
            ->where('order_id', $order->id)
            ->where('expires_at', '>', now())
            ->first();

        if (!$link) {
            abort(404);
        }

        $order->load(OrderService::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.order.prep_shown')
        );
    }
}
