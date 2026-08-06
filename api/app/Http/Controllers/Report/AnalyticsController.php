<?php

namespace App\Http\Controllers\Report;

use App\Exports\ArrayTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AnalyticsAbcRequest;
use App\Http\Requests\Report\AnalyticsAffiliatesRequest;
use App\Http\Requests\Report\AnalyticsCohortsRequest;
use App\Http\Requests\Report\AnalyticsCompareEventsRequest;
use App\Http\Requests\Report\AnalyticsCouponsRequest;
use App\Http\Requests\Report\AnalyticsEventAffinityRequest;
use App\Http\Requests\Report\AnalyticsFunnelRequest;
use App\Http\Requests\Report\AnalyticsInventoryRequest;
use App\Http\Requests\Report\AnalyticsLtvRequest;
use App\Http\Requests\Report\AnalyticsOverdueSalesRequest;
use App\Http\Requests\Report\AnalyticsPeriodRequest;
use App\Http\Requests\Report\AnalyticsRiskRequest;
use App\Http\Requests\Report\AnalyticsSalesByDimensionRequest;
use App\Http\Requests\Report\AnalyticsSalesSummaryRequest;
use App\Http\Requests\Report\AnalyticsTopRequest;
use App\Services\APIResponse;
use App\Services\Report\AnalyticsService;
use App\Services\Report\FunnelAnalyticsService;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $service,
        private FunnelAnalyticsService $funnelAnalyticsService,
    ) {}

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

        return APIResponse::success($data, __('messages.analytics.top_addons'));
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

    public function overdueOrders(AnalyticsOverdueSalesRequest $request)
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
            __('messages.analytics.overdue_sales'),
            200,
            [
                'pagination' => [
                    'current_page' => $list->currentPage(),
                    'per_page' => $list->perPage(),
                    'total' => $list->total(),
                    'last_page' => $list->lastPage(),
                ],
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

    public function churnClients()
    {
        $data = $this->service->churnClients(app('tenant_id'));

        return APIResponse::success($data, __('messages.analytics.churn_clients'));
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

    public function checkinInsights(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->checkinInsights(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.checkin_insights'));
    }

    /**
     * Vendas por dimensão configurável (roadmap Fase A1) — unifica
     * top-products/top-clients/by-channel num único endpoint aditivo; os
     * três antigos continuam ativos para não quebrar consumidores atuais.
     */
    public function salesByDimension(AnalyticsSalesByDimensionRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->salesByDimension(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['dimension'] ?? 'ticket_type',
            (int) ($validated['limit'] ?? 10)
        );

        return APIResponse::success($data, __('messages.analytics.sales_by_dimension'));
    }

    /** Exportação XLSX de vendas por dimensão (roadmap A2) — MESMA base de salesByDimension(). */
    public function salesByDimensionXlsx(AnalyticsSalesByDimensionRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->salesByDimension(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['dimension'] ?? 'ticket_type',
            (int) ($validated['limit'] ?? 10)
        );

        $rows = array_map(fn (array $item) => [
            $item['label'],
            $item['order_count'],
            $item['quantity_sold'],
            $item['revenue'],
        ], $data);

        return Excel::download(
            new ArrayTableExport(['Item', 'Pedidos', 'Quantidade vendida', 'Faturamento'], $rows),
            'vendas-por-dimensao-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function payments(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->paymentsSummary(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.payments_summary'));
    }

    /** Exportação XLSX do relatório de pagamentos (roadmap A2) — MESMA base de payments(). */
    public function paymentsXlsx(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->paymentsSummary(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        $rows = [
            ['Confirmadas', $data['confirmed']['count'], $data['confirmed']['total_amount']],
            ['Recusadas', $data['rejected']['count'], $data['rejected']['total_amount']],
            ['Aguardando aprovação', $data['pending_approval']['count'], $data['pending_approval']['total_amount']],
            ['Taxa de aprovação (%)', $data['approval_rate_percentage'], ''],
            ['Taxa de recusa (%)', $data['rejection_rate_percentage'], ''],
        ];

        return Excel::download(
            new ArrayTableExport(['Status', 'Quantidade', 'Valor total'], $rows),
            'relatorio-pagamentos-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * Relatório de afiliados consolidado (roadmap Fase A2) — ranking por
     * comissão gerada/vendas atribuídas + ROI.
     */
    public function affiliates(AnalyticsAffiliatesRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->affiliatesReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 20)
        );

        return APIResponse::success($data, __('messages.analytics.affiliates_report'));
    }

    /**
     * Relatório de cupons consolidado (roadmap Fase A2) — ranking de uso,
     * conversão, valor descontado e sinal de abuso.
     */
    public function coupons(AnalyticsCouponsRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->couponsReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 20)
        );

        return APIResponse::success($data, __('messages.analytics.coupons_report'));
    }

    /**
     * Relatório de reembolsos/chargebacks dedicado (roadmap Fase A2).
     */
    public function refunds(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->refundsReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.refunds_report'));
    }

    /**
     * Relatório de inventário/assentos avançado (roadmap Fase A2) —
     * ocupação por setor de um evento específico. `event_uuid` obrigatório.
     */
    public function inventory(AnalyticsInventoryRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->inventoryReport(app('tenant_id'), $validated['event_uuid']);

        return APIResponse::success($data, __('messages.analytics.inventory_report'));
    }

    /**
     * Comparação entre eventos (roadmap Fase A2, último item) — curva de
     * vendas normalizada por "dias desde abertura de vendas" de cada
     * evento (não data de calendário), mais totais comparativos. Ver
     * App\Services\Report\AnalyticsService::compareEvents().
     */
    public function compareEvents(AnalyticsCompareEventsRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->compareEvents(app('tenant_id'), $validated['event_uuids']);

        return APIResponse::success($data, __('messages.analytics.compare_events_report'));
    }

    /**
     * Funil de conversão anônimo (roadmap A2) — sessões únicas por etapa e
     * taxa de conversão entre etapas consecutivas. Ver
     * App\Services\Report\FunnelAnalyticsService.
     */
    public function funnel(AnalyticsFunnelRequest $request)
    {
        $validated = $request->validated();

        $data = $this->funnelAnalyticsService->funnel(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['event_uuid'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.funnel_report'));
    }

    /**
     * Relatório de antifraude (roadmap Fase A3) — consome `RiskEngineService`
     * já persistido em `sales.risk_flagged`/`risk_reason`, só leitura.
     */
    public function risk(AnalyticsRiskRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->riskReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (int) ($validated['limit'] ?? 50)
        );

        return APIResponse::success($data, __('messages.analytics.risk_report'));
    }

    /**
     * Relatório de revenda/transferência (roadmap Fase A3) — volume/valor
     * de revenda oficial, status de repasse e transferências simples.
     */
    public function resale(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->resaleReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.resale_report'));
    }

    /**
     * Relatório de bilheteria por operador/terminal (roadmap Fase A3) —
     * check-ins por operador/portaria + vendas presenciais por operador.
     */
    public function operators(AnalyticsPeriodRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->operatorReport(
            app('tenant_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.operator_report'));
    }

    /**
     * Coortes de retenção (roadmap Fase A3, parte 2) — `from` obrigatório
     * (regra transversal de filtro ativo). Ver
     * App\Services\Report\AnalyticsService::cohortsReport().
     */
    public function cohorts(AnalyticsCohortsRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->cohortsReport(
            app('tenant_id'),
            $validated['from'],
            $validated['to'] ?? null
        );

        return APIResponse::success($data, __('messages.analytics.cohorts_report'));
    }

    /**
     * LTV histórico (roadmap Fase A3, parte 2) — realizado, agregado por
     * segmento RFM ou coorte de aquisição. Ver
     * App\Services\Report\AnalyticsService::ltvReport().
     */
    public function ltv(AnalyticsLtvRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->ltvReport(
            app('tenant_id'),
            $validated['group_by'] ?? 'segment'
        );

        return APIResponse::success($data, __('messages.analytics.ltv_report'));
    }

    /**
     * Afinidade entre eventos (roadmap Fase A3, parte 2) — `event_uuid`
     * obrigatório. Ver App\Services\Report\AnalyticsService::eventAffinityReport().
     */
    public function eventAffinity(AnalyticsEventAffinityRequest $request)
    {
        $validated = $request->validated();

        $data = $this->service->eventAffinityReport(
            app('tenant_id'),
            $validated['event_uuid'],
            (int) ($validated['limit'] ?? 10)
        );

        return APIResponse::success($data, __('messages.analytics.event_affinity_report'));
    }
}
