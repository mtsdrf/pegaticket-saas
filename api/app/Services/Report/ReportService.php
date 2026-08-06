<?php

namespace App\Services\Report;

use App\Models\Sale\Sale;
use App\Support\GridQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Leitura agregada de Sale/Client, sem tabela própria. Venda cancelado
 * (cancelled_at preenchido) nunca conta em nenhum indicador/gráfico/
 * relatório — decisão registrada em `.claude/memory/architecture-decisions.md`.
 * Toda query aqui filtra `tenant_id` explicitamente (sem exceção) e usa
 * Query Builder/Eloquent com binding, nunca concatenação de string (o
 * legado tinha SQL injection real nesses endpoints — ver
 * `.claude/memory/database-analysis/06-business-rules.md`).
 */
class ReportService
{
    public function __construct(
        private readonly RfmCalculator $rfmCalculator = new RfmCalculator
    ) {}

    public function indicators(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $totalOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->count();
        $completedOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();
        $paidOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();

        $totalAmount = (float) $this->salesQuery($tenantId, $dateFrom, $dateTo)->sum('total_amount');
        $amountReceived = $this->amountReceived($tenantId, $dateFrom, $dateTo);
        $averageTicket = $totalOrders > 0 ? $totalAmount / $totalOrders : 0.0;
        $comparison = $this->salesComparison($tenantId, $dateFrom, $dateTo);
        $occupancy = $this->occupancyRate($tenantId);

        return [
            'total_sales' => $totalOrders,
            'total_sales_amount' => $this->formatMoney($totalAmount),
            'average_ticket' => $this->formatMoney($averageTicket),
            'completed_sales' => $completedOrders,
            'uncompleted_sales' => $totalOrders - $completedOrders,
            'paid_sales' => $paidOrders,
            'unpaid_sales' => $totalOrders - $paidOrders,
            'amount_received' => $this->formatMoney($amountReceived),
            'amount_receivable' => $this->formatMoney($totalAmount - $amountReceived),
            'net_revenue_amount' => $this->formatMoney($this->netRevenue($tenantId, $dateFrom, $dateTo)),
            'current_period_total_amount' => $this->formatMoney($comparison['current_total']),
            'previous_period_total_amount' => $this->formatMoney($comparison['previous_total']),
            'sales_growth_percentage' => $comparison['growth_percentage'],
            'comparison_current_label' => $comparison['current_label'],
            'comparison_previous_label' => $comparison['previous_label'],
            'overdue_sales_count' => $this->overdueOrdersCount($tenantId),
            'tickets_issued' => $occupancy['tickets_issued'],
            'commercial_capacity' => $occupancy['commercial_capacity'],
            'occupancy_percentage' => $occupancy['occupancy_percentage'],
        ];
    }

    /**
     * Receita líquida básica (KPI 2, roadmap Fase A1) — SÓ realizado, sem
     * projeção. Soma `receivables.net_amount` (gross - taxa da plataforma -
     * taxa do processador) das vendas do tenant cuja `created_at` cai no
     * período — `Receivable` só é gerado para venda paga
     * (`ReceivableGenerationService::generateForSaleUuid`), então isto já é
     * "receita líquida do que foi de fato recebido", não uma projeção.
     */
    private function netRevenue(int $tenantId, ?string $dateFrom, ?string $dateTo): float
    {
        return (float) DB::table('receivables')
            ->join('sales', 'sales.id', '=', 'receivables.sale_id')
            ->where('receivables.tenant_id', $tenantId)
            ->whereNull('receivables.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->sum('receivables.net_amount');
    }

    /**
     * Ocupação comercial (KPI 4, roadmap Fase A1) — snapshot atual do
     * tenant, NÃO filtrado por período (capacidade é atributo estrutural do
     * `TicketType`, não um fato datado como venda). `tickets_issued` conta
     * só tickets `status = 'ativo'` (exclui estornado/cancelado) cujo
     * `ticket_type` tem capacidade cadastrada (`quantity_available` não
     * nulo) — lote sem capacidade definida (venda ilimitada) fica fora do
     * denominador E do numerador, para não subestimar a ocupação dos lotes
     * que de fato têm limite.
     */
    private function occupancyRate(int $tenantId): array
    {
        $capacity = (int) DB::table('ticket_types')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('quantity_available')
            ->sum('quantity_available');

        $issued = (int) DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where('tickets.tenant_id', $tenantId)
            ->whereNull('tickets.deleted_at')
            ->where('tickets.status', 'ativo')
            ->whereNotNull('ticket_types.quantity_available')
            ->whereNull('ticket_types.deleted_at')
            ->count();

        return [
            'tickets_issued' => $issued,
            'commercial_capacity' => $capacity,
            'occupancy_percentage' => $capacity > 0 ? round(($issued / $capacity) * 100, 2) : 0.0,
        ];
    }

    public function charts(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $totalOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->count();
        $completedOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();
        $paidOrders = $this->salesQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();

        $totalAmount = (float) $this->salesQuery($tenantId, $dateFrom, $dateTo)->sum('total_amount');
        $amountReceived = $this->amountReceived($tenantId, $dateFrom, $dateTo);

        return [
            'sales_by_month' => $this->salesByMonth($tenantId, $dateFrom, $dateTo),
            'paid_vs_unpaid' => [
                'paid' => $paidOrders,
                'unpaid' => $totalOrders - $paidOrders,
            ],
            'completed_vs_uncompleted' => [
                'completed' => $completedOrders,
                'uncompleted' => $totalOrders - $completedOrders,
            ],
            'received_vs_receivable' => [
                'received' => $this->formatMoney($amountReceived),
                'receivable' => $this->formatMoney($totalAmount - $amountReceived),
            ],
            'sales_by_city' => [],
            'sales_by_neighborhood' => [],
            'seasonality_matrix' => $this->seasonalityMatrix($tenantId),
            'top_ticket_types' => $this->topTicketTypes($tenantId, $dateFrom, $dateTo),
            'top_clients' => $this->topClients($tenantId, $dateFrom, $dateTo),
            'rfm_clients' => $this->rfmClients($tenantId, $dateFrom, $dateTo),
            'late_payment_clients' => $this->latePaymentClients($tenantId, $dateFrom, $dateTo),
            'overdue_sales' => $this->overdueOrders($tenantId),
            'receivables_aging' => $this->receivablesAging($tenantId),
            'receivables_forecast_by_month' => $this->receivablesForecastByMonth($tenantId),
            'abc_ticket_types' => $this->abcTicketTypes($tenantId, $dateFrom, $dateTo),
            'abc_clients' => $this->abcClients($tenantId, $dateFrom, $dateTo),
        ];
    }

    public function filteredOrders(
        int $tenantId,
        array $filters,
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $sortable = [
            'is_paid' => 'sales.is_paid',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;

        return $this->salesEloquentQuery($tenantId, $filters)
            ->orderBy($sortColumn ?? 'sales.id', GridQuery::normalizeSortDir($sortDir))
            ->paginate($perPage);
    }

    /**
     * Resumo agregado (não paginado) do relatório de vendas, sobre a
     * MESMA base filtrada de filteredOrders() (salesEloquentQuery), só
     * trocando paginação por contagem.
     *
     * overdue_percentage é uma definição SIMPLIFICADA: só vendas com
     * `is_paid = false AND is_installment = false AND due_date < hoje` —
     * NÃO cobre parcelas atrasadas de venda parcelado (essa lógica de
     * union por installment vive em overdueOrdersCount() e não é replicada
     * aqui para não duplicá-la; este summary usa apenas o campo direto
     * sales.due_date, consistente com o resto de salesEloquentQuery).
     */
    public function salesFilteredSummary(int $tenantId, array $filters): array
    {
        $total = $this->salesEloquentQuery($tenantId, $filters)->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'completed_percentage' => 0.0,
                'paid_percentage' => 0.0,
                'overdue_percentage' => 0.0,
            ];
        }

        $completed = $this->salesEloquentQuery($tenantId, $filters)->where('is_paid', true)->count();
        $paid = $this->salesEloquentQuery($tenantId, $filters)->where('is_paid', true)->count();
        $overdue = $this->salesEloquentQuery($tenantId, $filters)
            ->where('is_paid', false)
            ->where('is_installment', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return [
            'total' => $total,
            'completed_percentage' => round(($completed / $total) * 100, 2),
            'paid_percentage' => round(($paid / $total) * 100, 2),
            'overdue_percentage' => round(($overdue / $total) * 100, 2),
        ];
    }

    /**
     * Resultado por canal (roadmap A1.3, `sales.origin`) — agregado por
     * origin (staff/storefront e canais históricos), sobre a MESMA base de
     * salesQuery() (venda cancelado/soft-deletado sempre excluído, mesmo
     * filtro de período por created_at dos outros indicadores). Um bucket
     * por origin com venda no período, ordenado por revenue desc.
     *
     * @return list<array{origin: string, order_count: int, total_amount: string, average_ticket: string}>
     */
    public function byChannel(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $normalizedOriginSql = "CASE WHEN sales.origin = 'counter' THEN 'staff' ELSE sales.origin END";

        $rows = $this->salesQuery($tenantId, $dateFrom, $dateTo)
            ->selectRaw("{$normalizedOriginSql} as origin, COUNT(*) as order_count, SUM(sales.total_amount) as total_amount")
            ->groupByRaw($normalizedOriginSql)
            ->orderByDesc('total_amount')
            ->get();

        return $rows->map(function ($row) {
            $orderCount = (int) $row->order_count;
            $totalAmount = (float) $row->total_amount;
            $averageTicket = $orderCount > 0 ? $totalAmount / $orderCount : 0.0;

            return [
                'origin' => Sale::normalizeOrigin((string) $row->origin),
                'order_count' => $orderCount,
                'total_amount' => $this->formatMoney($totalAmount),
                'average_ticket' => $this->formatMoney($averageTicket),
            ];
        })->all();
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function generateSalesPdf(int $tenantId, array $filters): array
    {
        $sales = $this->salesEloquentQuery($tenantId, $filters)->orderByDesc('id')->get();

        $pdf = Pdf::loadView('reports.sales-pdf', [
            'sales' => $sales,
            'tenantName' => tenant()?->name,
            'generatedAt' => now(),
        ]);

        return [
            'content' => $pdf->output(),
            'filename' => 'relatorio-vendas-'.now()->format('Ymd_His').'.pdf',
        ];
    }

    /**
     * Linhas do relatório de vendas para exportação XLSX (roadmap A2) —
     * MESMA base filtrada de generateSalesPdf/filteredOrders
     * (salesEloquentQuery), só reformatada como headings+rows em vez de
     * PDF/paginação.
     *
     * @return array{headings: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    public function salesRowsForExport(int $tenantId, array $filters): array
    {
        $sales = $this->salesEloquentQuery($tenantId, $filters)->orderByDesc('id')->get();

        $headings = ['Código', 'Cliente', 'Origem', 'Status', 'Pago', 'Valor total', 'Criado em'];

        $rows = $sales->map(fn (Sale $sale) => [
            $sale->uuid,
            $sale->finalCustomer?->name ?? '—',
            Sale::normalizeOrigin((string) $sale->origin),
            $sale->status,
            $sale->is_paid ? 'Sim' : 'Não',
            (float) $sale->total_amount,
            optional($sale->created_at)->format('d/m/Y H:i'),
        ])->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * Base de vendas ativos (não cancelados, não soft-deletados) do
     * tenant, com filtro opcional de período por `created_at`.
     */
    private function salesQuery(int $tenantId, ?string $dateFrom, ?string $dateTo): QueryBuilder
    {
        return DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo));
    }

    /**
     * Valor recebido = soma de total_amount dos vendas não-parcelados
     * pagos + soma de amount das parcelas pagas (via join com sales não
     * cancelados do tenant). Sempre agregado via SQL (sum/join), nunca
     * carreganda vendas um a um em PHP.
     */
    private function amountReceived(int $tenantId, ?string $dateFrom, ?string $dateTo): float
    {
        $nonInstallment = (float) $this->salesQuery($tenantId, $dateFrom, $dateTo)
            ->where('is_installment', false)
            ->where('is_paid', true)
            ->sum('total_amount');

        $installments = (float) DB::table('sale_installments')
            ->join('sales', 'sales.id', '=', 'sale_installments.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', true)
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->sum('sale_installments.amount');

        return $nonInstallment + $installments;
    }

    /**
     * Agrupamento por mês feito em PHP a partir de uma única coluna
     * (`created_at`) já filtrada por tenant/período/cancelamento no SQL —
     * evita função de data específica de engine (`DATE_FORMAT` do MySQL
     * vs `strftime` do SQLite usado nos testes), sem carregar as linhas
     * inteiras de venda em memória.
     */
    private function salesByMonth(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        $grouped = [];

        foreach ($this->salesQuery($tenantId, $dateFrom, $dateTo)->get(['created_at', 'total_amount']) as $row) {
            $month = Carbon::parse($row->created_at)->format('Y-m');

            if (! isset($grouped[$month])) {
                $grouped[$month] = ['count' => 0, 'total_amount' => 0.0];
            }

            $grouped[$month]['count']++;
            $grouped[$month]['total_amount'] += (float) $row->total_amount;
        }

        ksort($grouped);

        return collect($grouped)->map(fn ($data, $month) => [
            'month' => $month,
            'count' => (int) $data['count'],
            'total_amount' => $this->formatMoney($data['total_amount']),
        ])->values()->all();
    }

    private function salesByCity(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        return [];
    }

    private function salesByNeighborhood(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        return [];
    }

    private function seasonalityMatrix(int $tenantId): array
    {
        $grouped = [];

        foreach ($this->salesQuery($tenantId, null, null)->get(['created_at', 'total_amount']) as $row) {
            $date = Carbon::parse($row->created_at);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            if (! isset($grouped[$year])) {
                $grouped[$year] = [];
            }

            if (! isset($grouped[$year][$month])) {
                $grouped[$year][$month] = ['count' => 0, 'total_amount' => 0.0];
            }

            $grouped[$year][$month]['count']++;
            $grouped[$year][$month]['total_amount'] += (float) $row->total_amount;
        }

        krsort($grouped);

        return collect($grouped)->map(function (array $months, int $year) {
            return [
                'year' => $year,
                'months' => collect(range(1, 12))->map(function (int $month) use ($months) {
                    $data = $months[$month] ?? ['count' => 0, 'total_amount' => 0.0];

                    return [
                        'month' => $month,
                        'count' => (int) $data['count'],
                        'total_amount' => $this->formatMoney($data['total_amount']),
                    ];
                })->all(),
            ];
        })->values()->all();
    }

    private function topTicketTypes(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('ticket_types.deleted_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->groupBy('ticket_types.id', 'ticket_types.name')
            ->selectRaw('ticket_types.name as ticket_type_name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'ticket_type_name' => $row->ticket_type_name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();
    }

    private function topClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        return DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->groupBy('final_customers.id', 'final_customers.name')
            ->selectRaw('final_customers.name as client_name, COUNT(sales.id) as order_count, SUM(sales.total_amount) as total_amount')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'client_name' => $row->client_name,
                'order_count' => (int) $row->order_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();
    }

    /**
     * Segmento calculado por `RfmCalculator` (tercis sobre TODOS os
     * clientes ativos no período, não só o top N retornado) — mesma
     * fórmula usada por `AnalyticsService::topClients()`.
     */
    private function rfmClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->groupBy('final_customers.id', 'final_customers.name')
            ->selectRaw('final_customers.name as client_name, COUNT(sales.id) as frequency, SUM(sales.total_amount) as monetary, MAX(sales.created_at) as last_order_at')
            ->orderByDesc('monetary')
            ->get();

        $clients = $rows->map(fn ($row) => [
            'client_name' => $row->client_name,
            'frequency' => (int) $row->frequency,
            'monetary' => (float) $row->monetary,
            'recency_days' => Carbon::parse($row->last_order_at)->diffInDays(now()),
        ]);

        $segments = $this->rfmCalculator->segments($clients->all());
        $segments8 = $this->rfmCalculator->segments8($clients->all());

        return $clients
            ->values()
            ->map(fn (array $client, int $index) => [
                'client_name' => $client['client_name'],
                'frequency' => $client['frequency'],
                'monetary' => $this->formatMoney($client['monetary']),
                'recency_days' => $client['recency_days'],
                'segment' => $this->rfmCalculator->displayLabel($segments[$index]),
                'segment8' => $segments8[$index],
                'segment8_label' => $this->rfmCalculator->displaySegment8Label($segments8[$index]),
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    private function latePaymentClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNotNull('sales.paid_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->get(['final_customers.name as client_name', 'sales.created_at', 'sales.paid_at']);

        return $rows
            ->groupBy('client_name')
            ->map(function ($clientRows, $clientName) {
                $days = collect($clientRows)->map(function ($row) {
                    return Carbon::parse($row->created_at)->diffInDays(Carbon::parse($row->paid_at));
                });

                return [
                    'client_name' => $clientName,
                    'avg_days_to_pay' => round($days->avg() ?? 0, 1),
                    'paid_sales_count' => $days->count(),
                ];
            })
            ->sortByDesc('avg_days_to_pay')
            ->take($limit)
            ->values()
            ->all();
    }

    private function overdueOrders(int $tenantId, int $limit = 5): array
    {
        $installmentRows = DB::table('sale_installments')
            ->join('sales', 'sales.id', '=', 'sale_installments.sale_id')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', false)
            ->whereDate('sale_installments.due_date', '<', now()->toDateString())
            ->select(
                'sales.uuid as sale_uuid',
                'final_customers.name as client_name',
                'sale_installments.amount as amount',
                'sale_installments.due_date as due_date'
            )
            ->get()
            ->map(fn ($row) => [
                'sale_uuid' => $row->sale_uuid,
                'client_name' => $row->client_name,
                'amount' => $this->formatMoney((float) $row->amount),
                'due_date' => Carbon::parse($row->due_date)->toDateString(),
                'days_overdue' => Carbon::parse($row->due_date)->diffInDays(now()),
                'source' => 'installment',
            ]);

        $orderRows = DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->where('sales.is_installment', false)
            ->where('sales.is_paid', false)
            ->whereNotNull('sales.due_date')
            ->whereDate('sales.due_date', '<', now()->toDateString())
            ->select(
                'sales.uuid as sale_uuid',
                'final_customers.name as client_name',
                'sales.total_amount as amount',
                'sales.due_date as due_date'
            )
            ->get()
            ->map(fn ($row) => [
                'sale_uuid' => $row->sale_uuid,
                'client_name' => $row->client_name,
                'amount' => $this->formatMoney((float) $row->amount),
                'due_date' => Carbon::parse($row->due_date)->toDateString(),
                'days_overdue' => Carbon::parse($row->due_date)->diffInDays(now()),
                'source' => 'order',
            ]);

        return $installmentRows
            ->concat($orderRows)
            ->sortByDesc('days_overdue')
            ->take($limit)
            ->values()
            ->all();
    }

    private function receivablesAging(int $tenantId): array
    {
        $today = now()->startOfDay();
        $buckets = [
            'current' => ['bucket' => 'current', 'label' => 'A vencer', 'amount' => 0.0, 'count' => 0],
            'overdue_1_30' => ['bucket' => 'overdue_1_30', 'label' => 'Vencido 1-30 dias', 'amount' => 0.0, 'count' => 0],
            'overdue_31_60' => ['bucket' => 'overdue_31_60', 'label' => 'Vencido 31-60 dias', 'amount' => 0.0, 'count' => 0],
            'overdue_61_90' => ['bucket' => 'overdue_61_90', 'label' => 'Vencido 61-90 dias', 'amount' => 0.0, 'count' => 0],
            'overdue_90_plus' => ['bucket' => 'overdue_90_plus', 'label' => 'Vencido 90+ dias', 'amount' => 0.0, 'count' => 0],
        ];

        $rows = DB::table('sale_installments')
            ->join('sales', 'sales.id', '=', 'sale_installments.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', false)
            ->get(['sale_installments.amount', 'sale_installments.due_date'])
            ->map(fn ($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            ->concat(
                DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNull('cancelled_at')
                    ->where('is_installment', false)
                    ->where('is_paid', false)
                    ->whereNotNull('due_date')
                    ->get(['total_amount as amount', 'due_date'])
                    ->map(fn ($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            );

        foreach ($rows as $row) {
            if (! $row['due_date']) {
                continue;
            }

            $dueDate = Carbon::parse($row['due_date'])->startOfDay();
            $daysOverdue = $dueDate->lt($today) ? $dueDate->diffInDays($today) : 0;

            $bucketKey = match (true) {
                $daysOverdue === 0 => 'current',
                $daysOverdue <= 30 => 'overdue_1_30',
                $daysOverdue <= 60 => 'overdue_31_60',
                $daysOverdue <= 90 => 'overdue_61_90',
                default => 'overdue_90_plus',
            };

            $buckets[$bucketKey]['amount'] += $row['amount'];
            $buckets[$bucketKey]['count']++;
        }

        return collect($buckets)->map(fn ($bucket) => [
            ...$bucket,
            'amount' => $this->formatMoney($bucket['amount']),
            'count' => (int) $bucket['count'],
        ])->values()->all();
    }

    private function receivablesForecastByMonth(int $tenantId): array
    {
        $grouped = [];

        $rows = DB::table('sale_installments')
            ->join('sales', 'sales.id', '=', 'sale_installments.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', false)
            ->get(['sale_installments.amount', 'sale_installments.due_date'])
            ->map(fn ($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            ->concat(
                DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNull('cancelled_at')
                    ->where('is_installment', false)
                    ->where('is_paid', false)
                    ->whereNotNull('due_date')
                    ->get(['total_amount as amount', 'due_date'])
                    ->map(fn ($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            );

        foreach ($rows as $row) {
            if (! $row['due_date']) {
                continue;
            }

            $month = Carbon::parse($row['due_date'])->format('Y-m');

            if (! isset($grouped[$month])) {
                $grouped[$month] = ['count' => 0, 'total_amount' => 0.0];
            }

            $grouped[$month]['count']++;
            $grouped[$month]['total_amount'] += (float) $row['amount'];
        }

        ksort($grouped);

        return collect($grouped)->map(fn ($data, $month) => [
            'month' => $month,
            'count' => (int) $data['count'],
            'total_amount' => $this->formatMoney($data['total_amount']),
        ])->values()->all();
    }

    private function abcTicketTypes(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->whereNull('ticket_types.deleted_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->groupBy('ticket_types.id', 'ticket_types.name')
            ->selectRaw('ticket_types.name as ticket_type_name, SUM(sale_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => ['ticket_type_name' => $row->ticket_type_name, 'revenue' => (float) $row->revenue]);

        return $this->abcCurve($rows->all(), 'ticket_type_name', $limit);
    }

    private function abcClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('sales')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.created_at', '<=', $dateTo))
            ->groupBy('final_customers.id', 'final_customers.name')
            ->selectRaw('final_customers.name as client_name, SUM(sales.total_amount) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => ['client_name' => $row->client_name, 'revenue' => (float) $row->revenue]);

        return $this->abcCurve($rows->all(), 'client_name', $limit);
    }

    private function abcCurve(array $rows, string $nameKey, int $limit): array
    {
        $totalRevenue = collect($rows)->sum('revenue');
        $cumulative = 0.0;

        return collect($rows)->map(function (array $row) use ($totalRevenue, &$cumulative, $nameKey) {
            $participation = $totalRevenue > 0 ? round(($row['revenue'] / $totalRevenue) * 100, 2) : 0.0;
            $cumulative = round($cumulative + $participation, 2);

            return [
                $nameKey => $row[$nameKey],
                'revenue' => $this->formatMoney($row['revenue']),
                'participation_percentage' => $participation,
                'cumulative_percentage' => $cumulative,
                'curve_class' => $this->abcCurveClass($cumulative),
            ];
        })->take($limit)->values()->all();
    }

    private function overdueOrdersCount(int $tenantId): int
    {
        $today = now()->toDateString();

        $overdueInstallments = DB::table('sale_installments')
            ->join('sales', 'sales.id', '=', 'sale_installments.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', false)
            ->whereDate('sale_installments.due_date', '<', $today)
            ->count();

        $overdueOrders = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('is_installment', false)
            ->where('is_paid', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        return $overdueInstallments + $overdueOrders;
    }

    private function salesComparison(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        [$currentStart, $currentEnd, $previousStart, $previousEnd, $currentLabel, $previousLabel] =
            $this->comparisonPeriods($dateFrom, $dateTo);

        $currentTotal = (float) $this->salesQuery(
            $tenantId,
            $currentStart->toDateString(),
            $currentEnd->toDateString()
        )->sum('total_amount');

        $previousTotal = (float) $this->salesQuery(
            $tenantId,
            $previousStart->toDateString(),
            $previousEnd->toDateString()
        )->sum('total_amount');

        $growthPercentage = $previousTotal > 0
            ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 2)
            : null;

        return [
            'current_total' => $currentTotal,
            'previous_total' => $previousTotal,
            'growth_percentage' => $growthPercentage,
            'current_label' => $currentLabel,
            'previous_label' => $previousLabel,
        ];
    }

    private function comparisonPeriods(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom || $dateTo) {
            $currentStart = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::parse($dateTo)->startOfDay();
            $currentEnd = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::parse($dateFrom)->endOfDay();

            if ($currentEnd->lt($currentStart)) {
                [$currentStart, $currentEnd] = [$currentEnd->copy()->startOfDay(), $currentStart->copy()->endOfDay()];
            }

            $days = $currentStart->diffInDays($currentEnd) + 1;
            $previousEnd = $currentStart->copy()->subDay()->endOfDay();
            $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

            return [
                $currentStart,
                $currentEnd,
                $previousStart,
                $previousEnd,
                $currentStart->format('d/m/Y').' a '.$currentEnd->format('d/m/Y'),
                $previousStart->format('d/m/Y').' a '.$previousEnd->format('d/m/Y'),
            ];
        }

        $currentStart = now()->startOfMonth();
        $currentEnd = now()->endOfMonth();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = now()->subMonthNoOverflow()->endOfMonth();

        return [
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd,
            $currentStart->translatedFormat('F/Y'),
            $previousStart->translatedFormat('F/Y'),
        ];
    }

    private function abcCurveClass(float $cumulativePercentage): string
    {
        if ($cumulativePercentage <= 80) {
            return 'A';
        }

        if ($cumulativePercentage <= 95) {
            return 'B';
        }

        return 'C';
    }

    /**
     * Filtros: client_uuid, client_name, is_paid, date_from, date_to (por
     * created_at). Cancelado sempre excluído — não há filtro para incluir
     * cancelados neste endpoint (ver contrato em routes/api.php).
     */
    private function salesEloquentQuery(int $tenantId, array $filters): Builder
    {
        $query = Sale::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            // finalCustomerLink NÃO entra no with() de propósito — ver
            // comentário em SaleService::EAGER_RELATIONS (relation
            // depende de tenant_id da instância real, quebra em eager
            // load).
            ->with(['finalCustomer']);

        if (! empty($filters['client_uuid'])) {
            $query->whereHas('finalCustomer', fn ($q) => $q->where('uuid', $filters['client_uuid']));
        }

        if (! empty($filters['client_name'])) {
            $query->whereHas('finalCustomer', fn ($q) => $q->where('name', 'like', '%'.$filters['client_name'].'%'));
        }

        if (array_key_exists('is_paid', $filters) && $filters['is_paid'] !== null) {
            $query->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Filtro por canal (roadmap A1.3) — drill-down do by-channel:
        // GET /reports/sales?origin=X&date_from=Y&date_to=Z.
        if (! empty($filters['origin'])) {
            $normalizedOrigin = Sale::normalizeOrigin((string) $filters['origin']);

            if ($normalizedOrigin === 'staff') {
                $query->whereIn('sales.origin', ['staff', 'counter']);
            } else {
                $query->where('sales.origin', $normalizedOrigin);
            }
        }

        return $query;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
