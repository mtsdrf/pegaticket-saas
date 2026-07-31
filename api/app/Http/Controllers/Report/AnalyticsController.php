<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AnalyticsAbcRequest;
use App\Http\Requests\Report\AnalyticsOverdueOrdersRequest;
use App\Http\Requests\Report\AnalyticsPeriodRequest;
use App\Http\Requests\Report\AnalyticsSalesSummaryRequest;
use App\Http\Requests\Report\AnalyticsTopRequest;
use App\Services\APIResponse;
use App\Services\Report\AnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $service
    ) {
    }

    public function salesSummary(AnalyticsSalesSummaryRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->salesSummary(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['group_by'] ?? 'month'
        );

        return APIResponse::success($data, __('messages.analytics.sales_summary'));
    }

    public function topProducts(AnalyticsTopRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->topTicketTypes(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 10)
        );

        return APIResponse::success($data, __('messages.analytics.top_products'));
    }

    public function salesByLocation(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->salesByLocation(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.sales_by_location'));
    }

    public function salesHistory()
    {
        $data = $this->service->salesHistory(app('tenant_id'));

        return APIResponse::success($data, __('messages.analytics.sales_history'));
    }

    public function topClients(AnalyticsTopRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->topClients(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 10)
        );

        return APIResponse::success($data, __('messages.analytics.top_clients'));
    }

    public function paymentDelays(AnalyticsTopRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->paymentDelays(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 10)
        );

        return APIResponse::success($data, __('messages.analytics.payment_delays'));
    }

    public function overdueOrders(AnalyticsOverdueOrdersRequest $request)
    {
        $validated = $request->validated();

        $list = $this->service->overdueOrders(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['per_page'] ?? 15)
        );

        return APIResponse::success(
            $list->items(),
            __('messages.analytics.overdue_orders'),
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

    public function abcAnalysis(AnalyticsAbcRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->abcAnalysis(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['dimension'] ?? 'products'
        );

        return APIResponse::success($data, __('messages.analytics.abc_analysis'));
    }

    public function marginSummary(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->marginSummary(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.margin_summary'));
    }

    public function couponRoi(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->couponRoi(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.coupon_roi'));
    }

    public function revenueConcentration(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->revenueConcentration(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.revenue_concentration'));
    }

    public function deliveryOtif(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->deliveryOtif(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.delivery_otif'));
    }

    public function churnClients()
    {
        $data = $this->service->churnClients(app('tenant_id'));

        return APIResponse::success($data, __('messages.analytics.churn_clients'));
    }

    public function stalledProducts()
    {
        $data = $this->service->stalledTicketTypes(app('tenant_id'));

        return APIResponse::success($data, __('messages.analytics.stalled_products'));
    }

    public function stockRuptures()
    {
        $data = $this->service->stockRuptures(app('tenant_id'));

        return APIResponse::success($data, __('messages.analytics.stock_ruptures'));
    }

    public function salesByHour(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->salesByHour(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.sales_by_hour'));
    }
}
