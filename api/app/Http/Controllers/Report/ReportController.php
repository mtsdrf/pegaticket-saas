<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Http\Resources\Report\ClientReportListResource;
use App\Http\Resources\Report\ClientReportResource;
use App\Http\Resources\Report\CmvResource;
use App\Http\Resources\Report\ReceivableReportListResource;
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

    private const CLIENT_FILTERS = [
        'name',
        'phone_primary',
        'cidade_uuid',
        'bairro_uuid',
    ];

    private const RECEIVABLE_FILTERS = [
        'client_name',
        'amount_min',
        'amount_max',
        'is_overdue',
        'aging_bucket',
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

    public function clients(Request $request)
    {
        $list = $this->service->filteredClients(
            app('tenant_id'),
            $request->only(self::CLIENT_FILTERS),
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            ClientReportListResource::collection($list),
            __('messages.report.clients_list'),
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

    public function receivables(Request $request)
    {
        $list = $this->service->filteredReceivables(
            app('tenant_id'),
            $request->only(self::RECEIVABLE_FILTERS),
            (int) $request->get('per_page', 15),
            $request->get('sort_by'),
            (string) $request->get('sort_dir', 'asc')
        );

        return APIResponse::success(
            ReceivableReportListResource::collection($list),
            __('messages.report.receivables_list'),
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

    public function receivablesSummary()
    {
        $data = $this->service->receivablesSummary(app('tenant_id'));

        return APIResponse::success($data, __('messages.report.receivables_summary'));
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

    public function clientsPdf(Request $request)
    {
        $pdf = $this->service->generateClientsPdf(
            app('tenant_id'),
            $request->only(self::CLIENT_FILTERS)
        );

        return response()->streamDownload(
            fn() => print($pdf['content']),
            $pdf['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }
}
