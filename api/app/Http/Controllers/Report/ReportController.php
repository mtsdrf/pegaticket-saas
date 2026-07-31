<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Http\Resources\Report\CmvResource;
use App\Services\APIResponse;
use App\Services\Report\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const ORDER_FILTERS = [
        'client_uuid',
        'cidade_uuid',
        'bairro_uuid',
        'client_name',
        'is_paid',
        'is_delivered',
        'date_from',
        'date_to',
        'origin',
    ];

    public function __construct(
        private ReportService $service
    ) {
    }

    public function indicators(Request $request)
    {
        $data = $this->service->indicators(
            app('tenant_id'),
            $request->get('date_from'),
            $request->get('date_to')
        );

        return APIResponse::success($data, __('messages.report.indicators'));
    }

    public function charts(Request $request)
    {
        $data = $this->service->charts(
            app('tenant_id'),
            $request->get('date_from'),
            $request->get('date_to')
        );

        return APIResponse::success($data, __('messages.report.charts'));
    }

    public function operationHealth()
    {
        $data = $this->service->operationHealth(app('tenant_id'));

        return APIResponse::success($data, __('messages.report.operation_health'));
    }

    public function orders(Request $request)
    {
        $list = $this->service->filteredOrders(
            app('tenant_id'),
            $request->only(self::ORDER_FILTERS),
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'desc')
        );

        return APIResponse::success(
            OrderResource::collection($list),
            __('messages.report.orders_list'),
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

    public function ordersSummary(Request $request)
    {
        $data = $this->service->ordersFilteredSummary(
            app('tenant_id'),
            $request->only(self::ORDER_FILTERS)
        );

        return APIResponse::success($data, __('messages.report.orders_summary'));
    }

    /**
     * Resultado por canal (roadmap A1.3) — agregado por orders.origin.
     * Drill-down até o pedido reaproveita GET /orders?origin=X&date_from=
     * Y&date_to=Z, que já aceita esses filtros (nenhuma mudança necessária
     * ali, ver architecture-decisions.md).
     */
    public function byChannel(Request $request)
    {
        $data = $this->service->byChannel(
            app('tenant_id'),
            $request->get('date_from'),
            $request->get('date_to')
        );

        return APIResponse::success($data, __('messages.report.by_channel'));
    }

    public function cmv()
    {
        $data = $this->service->cmv(app('tenant_id'));

        return APIResponse::success(CmvResource::collection($data), __('messages.report.cmv'));
    }

    public function ordersPdf(Request $request)
    {
        $pdf = $this->service->generateOrdersPdf(
            app('tenant_id'),
            $request->only(self::ORDER_FILTERS)
        );

        return response()->streamDownload(
            fn() => print($pdf['content']),
            $pdf['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

}
