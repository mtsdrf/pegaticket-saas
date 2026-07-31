<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderListResource;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order\Order;
use App\Services\APIResponse;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;

/**
 * API pública (roadmap A6, item 20) — leitura de pedidos autenticada por
 * API key (middleware `api.key`, não JWT). Reaproveita OrderService/
 * OrderResource já usados no staff, só troca a autenticação: `tenant_id`
 * vem da API key resolvida (ApiKeyAccess), não do JWT (ResolveTenant).
 * Sem `perm:` — a posse da chave já é a autorização.
 */
class PublicOrderController extends Controller
{
    public function __construct(private OrderService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'is_paid', 'is_delivered']);

        $list = $this->service->paginate(
            app('tenant_id'),
            $filters,
            (int) $request->query('per_page', 15),
            $request->query('sort_by'),
            $request->query('sort_dir', 'desc')
        );

        return APIResponse::success(
            OrderListResource::collection($list),
            __('messages.public_api.orders_list'),
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

    public function show(Order $order)
    {
        $order = $this->service->find($order);
        $order->load(OrderService::EAGER_RELATIONS);

        return APIResponse::success(
            new OrderResource($order),
            __('messages.public_api.orders_show')
        );
    }
}
