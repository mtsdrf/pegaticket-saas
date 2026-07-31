<?php

namespace App\Services\Report;

use App\Models\Order\Order;
use App\Support\GridQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leitura agregada de Order/Client, sem tabela própria. Pedido cancelado
 * (cancelled_at preenchido) nunca conta em nenhum indicador/gráfico/
 * relatório — decisão registrada em `.claude/memory/architecture-decisions.md`.
 * Toda query aqui filtra `tenant_id` explicitamente (sem exceção) e usa
 * Query Builder/Eloquent com binding, nunca concatenação de string (o
 * legado tinha SQL injection real nesses endpoints — ver
 * `.claude/memory/database-analysis/06-business-rules.md`).
 */
class ReportService
{
    private const OPERATION_STAGE_THRESHOLDS = [
        'approval' => ['attention' => 3, 'critical' => 15],
        'production' => ['attention' => 12, 'critical' => 45],
        'dispatch' => ['attention' => 10, 'critical' => 35],
        'financial_pending' => ['attention' => 180, 'critical' => 1440],
    ];

    public function indicators(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $totalOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->count();
        $deliveredOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->where('is_delivered', true)->count();
        $paidOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();

        $totalAmount = (float) $this->ordersQuery($tenantId, $dateFrom, $dateTo)->sum('total_amount');
        $amountReceived = $this->amountReceived($tenantId, $dateFrom, $dateTo);
        $averageTicket = $totalOrders > 0 ? $totalAmount / $totalOrders : 0.0;
        $comparison = $this->salesComparison($tenantId, $dateFrom, $dateTo);

        return [
            'total_orders' => $totalOrders,
            'total_sales_amount' => $this->formatMoney($totalAmount),
            'average_ticket' => $this->formatMoney($averageTicket),
            'delivered_orders' => $deliveredOrders,
            'undelivered_orders' => $totalOrders - $deliveredOrders,
            'paid_orders' => $paidOrders,
            'unpaid_orders' => $totalOrders - $paidOrders,
            'amount_received' => $this->formatMoney($amountReceived),
            'amount_receivable' => $this->formatMoney($totalAmount - $amountReceived),
            'current_period_total_amount' => $this->formatMoney($comparison['current_total']),
            'previous_period_total_amount' => $this->formatMoney($comparison['previous_total']),
            'sales_growth_percentage' => $comparison['growth_percentage'],
            'comparison_current_label' => $comparison['current_label'],
            'comparison_previous_label' => $comparison['previous_label'],
            'overdue_orders_count' => $this->overdueOrdersCount($tenantId),
        ];
    }

    public function charts(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $totalOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->count();
        $deliveredOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->where('is_delivered', true)->count();
        $paidOrders = $this->ordersQuery($tenantId, $dateFrom, $dateTo)->where('is_paid', true)->count();

        $totalAmount = (float) $this->ordersQuery($tenantId, $dateFrom, $dateTo)->sum('total_amount');
        $amountReceived = $this->amountReceived($tenantId, $dateFrom, $dateTo);

        return [
            'orders_by_month' => $this->ordersByMonth($tenantId, $dateFrom, $dateTo),
            'paid_vs_unpaid' => [
                'paid' => $paidOrders,
                'unpaid' => $totalOrders - $paidOrders,
            ],
            'delivered_vs_undelivered' => [
                'delivered' => $deliveredOrders,
                'undelivered' => $totalOrders - $deliveredOrders,
            ],
            'received_vs_receivable' => [
                'received' => $this->formatMoney($amountReceived),
                'receivable' => $this->formatMoney($totalAmount - $amountReceived),
            ],
            'orders_by_city' => $this->ordersByCity($tenantId, $dateFrom, $dateTo),
            'orders_by_neighborhood' => $this->ordersByNeighborhood($tenantId, $dateFrom, $dateTo),
            'seasonality_matrix' => $this->seasonalityMatrix($tenantId),
            'top_products' => $this->topProducts($tenantId, $dateFrom, $dateTo),
            'top_clients' => $this->topClients($tenantId, $dateFrom, $dateTo),
            'rfm_clients' => $this->rfmClients($tenantId, $dateFrom, $dateTo),
            'late_payment_clients' => $this->latePaymentClients($tenantId, $dateFrom, $dateTo),
            'overdue_orders' => $this->overdueOrders($tenantId),
            'receivables_aging' => $this->receivablesAging($tenantId),
            'receivables_forecast_by_month' => $this->receivablesForecastByMonth($tenantId),
            'abc_products' => $this->abcProducts($tenantId, $dateFrom, $dateTo),
            'abc_clients' => $this->abcClients($tenantId, $dateFrom, $dateTo),
        ];
    }

    public function operationHealth(int $tenantId): array
    {
        $stageSummary = [
            'approval' => $this->emptyOperationStageSummary('approval', 'Aguardando aprovação'),
            'production' => $this->emptyOperationStageSummary('production', 'Em produção'),
            'dispatch' => $this->emptyOperationStageSummary('dispatch', 'Em expedição e entrega'),
            'financial_pending' => $this->emptyOperationStageSummary('financial_pending', 'Pendente financeiro'),
        ];

        foreach ($this->activeOperationOrders($tenantId) as $order) {
            $stage = $this->deriveOperationStageFromRow($order);
            if ($stage === null) {
                continue;
            }

            $ageInMinutes = $this->minutesSince($order->created_at);
            $stageSummary[$stage]['total']++;
            $stageSummary[$stage]['oldest_minutes'] = $stageSummary[$stage]['oldest_minutes'] === null
                ? $ageInMinutes
                : max($stageSummary[$stage]['oldest_minutes'], $ageInMinutes);

            $thresholds = self::OPERATION_STAGE_THRESHOLDS[$stage];

            if ($ageInMinutes >= $thresholds['critical']) {
                $stageSummary[$stage]['critical']++;
                continue;
            }

            if ($ageInMinutes >= $thresholds['attention']) {
                $stageSummary[$stage]['attention']++;
            }
        }

        $internalAttentionItems = collect($stageSummary)->sum(fn (array $stage) => $stage['attention'] + $stage['critical']);
        $internalCriticalItems = collect($stageSummary)->sum(fn (array $stage) => $stage['critical']);

        return [
            'internal' => $stageSummary,
            'totals' => [
                'items_requiring_attention' => $internalAttentionItems,
                'critical_items' => $internalCriticalItems,
            ],
        ];
    }

    public function filteredOrders(
        int $tenantId,
        array $filters,
        int $perPage = 15,
        ?string $sortBy = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator
    {
        $sortable = [
            'is_paid' => 'orders.is_paid',
            'is_delivered' => 'orders.is_delivered',
        ];

        $sortColumn = is_string($sortBy) ? ($sortable[$sortBy] ?? null) : null;

        return $this->ordersEloquentQuery($tenantId, $filters)
            ->orderBy($sortColumn ?? 'orders.id', GridQuery::normalizeSortDir($sortDir))
            ->paginate($perPage);
    }

    /**
     * Resumo agregado (não paginado) do relatório de pedidos, sobre a
     * MESMA base filtrada de filteredOrders() (ordersEloquentQuery), só
     * trocando paginação por contagem.
     *
     * overdue_percentage é uma definição SIMPLIFICADA: só pedidos com
     * `is_paid = false AND is_installment = false AND due_date < hoje` —
     * NÃO cobre parcelas atrasadas de pedido parcelado (essa lógica de
     * union por installment vive em overdueOrdersCount() e não é replicada
     * aqui para não duplicá-la; este summary usa apenas o campo direto
     * orders.due_date, consistente com o resto de ordersEloquentQuery).
     */
    public function ordersFilteredSummary(int $tenantId, array $filters): array
    {
        $total = $this->ordersEloquentQuery($tenantId, $filters)->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'delivered_percentage' => 0.0,
                'paid_percentage' => 0.0,
                'overdue_percentage' => 0.0,
            ];
        }

        $delivered = $this->ordersEloquentQuery($tenantId, $filters)->where('is_delivered', true)->count();
        $paid = $this->ordersEloquentQuery($tenantId, $filters)->where('is_paid', true)->count();
        $overdue = $this->ordersEloquentQuery($tenantId, $filters)
            ->where('is_paid', false)
            ->where('is_installment', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        return [
            'total' => $total,
            'delivered_percentage' => round(($delivered / $total) * 100, 2),
            'paid_percentage' => round(($paid / $total) * 100, 2),
            'overdue_percentage' => round(($overdue / $total) * 100, 2),
        ];
    }

    /**
     * Resultado por canal (roadmap A1.3, `orders.origin`) — agregado por
     * origin (staff/storefront e canais históricos), sobre a MESMA base de
     * ordersQuery() (pedido cancelado/soft-deletado sempre excluído, mesmo
     * filtro de período por created_at dos outros indicadores). Um bucket
     * por origin com pedido no período, ordenado por revenue desc.
     *
     * @return list<array{origin: string, order_count: int, total_amount: string, average_ticket: string}>
     */
    public function byChannel(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $normalizedOriginSql = "CASE WHEN orders.origin = 'counter' THEN 'staff' ELSE orders.origin END";

        $rows = $this->ordersQuery($tenantId, $dateFrom, $dateTo)
            ->selectRaw("{$normalizedOriginSql} as origin, COUNT(*) as order_count, SUM(orders.total_amount) as total_amount")
            ->groupByRaw($normalizedOriginSql)
            ->orderByDesc('total_amount')
            ->get();

        return $rows->map(function ($row) {
            $orderCount = (int) $row->order_count;
            $totalAmount = (float) $row->total_amount;
            $averageTicket = $orderCount > 0 ? $totalAmount / $orderCount : 0.0;

            return [
                'origin' => Order::normalizeOrigin((string) $row->origin),
                'order_count' => $orderCount,
                'total_amount' => $this->formatMoney($totalAmount),
                'average_ticket' => $this->formatMoney($averageTicket),
            ];
        })->all();
    }

    /**
     * @return array{content: string, filename: string}
     */
    public function generateOrdersPdf(int $tenantId, array $filters): array
    {
        $orders = $this->ordersEloquentQuery($tenantId, $filters)->orderByDesc('id')->get();

        $pdf = Pdf::loadView('reports.orders-pdf', [
            'orders' => $orders,
            'tenantName' => tenant()?->name,
            'generatedAt' => now(),
        ]);

        return [
            'content' => $pdf->output(),
            'filename' => 'relatorio-pedidos-' . now()->format('Ymd_His') . '.pdf',
        ];
    }

    /**
     * Base de pedidos ativos (não cancelados, não soft-deletados) do
     * tenant, com filtro opcional de período por `created_at`.
     */
    private function ordersQuery(int $tenantId, ?string $dateFrom, ?string $dateTo): QueryBuilder
    {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo));
    }

    /**
     * Pedidos ainda em fluxo operacional ou aguardando baixa financeira.
     * Mantém o mesmo recorte usado pela central operacional do front.
     */
    private function activeOperationOrders(int $tenantId): Collection
    {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->select([
                'status',
                'is_delivered',
                'is_paid',
                'is_out_for_delivery',
                'created_at',
            ])
            ->get();
    }

    /**
     * Espelha a regra do frontend para não existir divergência entre a fila
     * operacional e a telemetria agregada do dashboard.
     */
    private function deriveOperationStageFromRow(object $order): ?string
    {
        if (($order->status ?? null) === 'rejected') {
            return null;
        }

        if (($order->status ?? null) === 'pending_approval') {
            return 'approval';
        }

        if ((bool) ($order->is_delivered ?? false) === true && (bool) ($order->is_paid ?? false) === false) {
            return 'financial_pending';
        }

        if (
            ($order->status ?? null) === 'confirmed'
            && (bool) ($order->is_out_for_delivery ?? false) === true
            && (bool) ($order->is_delivered ?? false) === false
        ) {
            return 'dispatch';
        }

        if (
            ($order->status ?? null) === 'confirmed'
            && (bool) ($order->is_out_for_delivery ?? false) === false
            && (bool) ($order->is_delivered ?? false) === false
        ) {
            return 'production';
        }

        return null;
    }

    private function emptyOperationStageSummary(string $stage, string $label): array
    {
        return [
            'stage' => $stage,
            'label' => $label,
            'total' => 0,
            'attention' => 0,
            'critical' => 0,
            'oldest_minutes' => null,
        ];
    }

    private function minutesSince(mixed $dateTime): int
    {
        return max(0, Carbon::parse($dateTime)->diffInMinutes(now()));
    }

    /**
     * Valor recebido = soma de total_amount dos pedidos não-parcelados
     * pagos + soma de amount das parcelas pagas (via join com orders não
     * cancelados do tenant). Sempre agregado via SQL (sum/join), nunca
     * carregando pedidos um a um em PHP.
     */
    private function amountReceived(int $tenantId, ?string $dateFrom, ?string $dateTo): float
    {
        $nonInstallment = (float) $this->ordersQuery($tenantId, $dateFrom, $dateTo)
            ->where('is_installment', false)
            ->where('is_paid', true)
            ->sum('total_amount');

        $installments = (float) DB::table('order_installments')
            ->join('orders', 'orders.id', '=', 'order_installments.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', true)
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->sum('order_installments.amount');

        return $nonInstallment + $installments;
    }

    /**
     * Agrupamento por mês feito em PHP a partir de uma única coluna
     * (`created_at`) já filtrada por tenant/período/cancelamento no SQL —
     * evita função de data específica de engine (`DATE_FORMAT` do MySQL
     * vs `strftime` do SQLite usado nos testes), sem carregar as linhas
     * inteiras de pedido em memória.
     */
    private function ordersByMonth(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        $grouped = [];

        foreach ($this->ordersQuery($tenantId, $dateFrom, $dateTo)->get(['created_at', 'total_amount']) as $row) {
            $month = Carbon::parse($row->created_at)->format('Y-m');

            if (!isset($grouped[$month])) {
                $grouped[$month] = ['count' => 0, 'total_amount' => 0.0];
            }

            $grouped[$month]['count']++;
            $grouped[$month]['total_amount'] += (float) $row->total_amount;
        }

        ksort($grouped);

        return collect($grouped)->map(fn($data, $month) => [
            'month' => $month,
            'count' => (int) $data['count'],
            'total_amount' => $this->formatMoney($data['total_amount']),
        ])->values()->all();
    }

    private function ordersByCity(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        return DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->join('enderecos', 'enderecos.id', '=', 'clients.endereco_id')
            ->join('cidades', 'cidades.id', '=', 'enderecos.cidade_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('cidades.id', 'cidades.name')
            ->selectRaw('cidades.name as city_name, COUNT(*) as order_count, SUM(orders.total_amount) as total_amount')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($row) => [
                'city_name' => $row->city_name,
                'count' => (int) $row->order_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();
    }

    private function ordersByNeighborhood(int $tenantId, ?string $dateFrom, ?string $dateTo): array
    {
        return DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->join('enderecos', 'enderecos.id', '=', 'clients.endereco_id')
            ->join('bairros', 'bairros.id', '=', 'enderecos.bairro_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('bairros.id', 'bairros.name')
            ->selectRaw('bairros.name as neighborhood_name, COUNT(*) as order_count, SUM(orders.total_amount) as total_amount')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($row) => [
                'neighborhood_name' => $row->neighborhood_name,
                'count' => (int) $row->order_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();
    }

    private function seasonalityMatrix(int $tenantId): array
    {
        $grouped = [];

        foreach ($this->ordersQuery($tenantId, null, null)->get(['created_at', 'total_amount']) as $row) {
            $date = Carbon::parse($row->created_at);
            $year = (int) $date->format('Y');
            $month = (int) $date->format('n');

            if (!isset($grouped[$year])) {
                $grouped[$year] = [];
            }

            if (!isset($grouped[$year][$month])) {
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

    private function topProducts(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_items.deleted_at')
            ->whereNull('products.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name as product_name, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'product_name' => $row->product_name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();
    }

    private function topClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        return DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('clients.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.name as client_name, COUNT(orders.id) as order_count, SUM(orders.total_amount) as total_amount')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'client_name' => $row->client_name,
                'order_count' => (int) $row->order_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();
    }

    private function rfmClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('clients.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.name as client_name, COUNT(orders.id) as frequency, SUM(orders.total_amount) as monetary, MAX(orders.created_at) as last_order_at')
            ->orderByDesc('monetary')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $recencyDays = Carbon::parse($row->last_order_at)->diffInDays(now());

            return [
                'client_name' => $row->client_name,
                'frequency' => (int) $row->frequency,
                'monetary' => $this->formatMoney((float) $row->monetary),
                'recency_days' => $recencyDays,
                'segment' => $this->rfmSegment(
                    recencyDays: $recencyDays,
                    frequency: (int) $row->frequency,
                    monetary: (float) $row->monetary
                ),
            ];
        })->all();
    }

    private function latePaymentClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNotNull('orders.delivered_at')
            ->whereNotNull('orders.paid_at')
            ->whereNull('clients.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->get(['clients.name as client_name', 'orders.delivered_at', 'orders.paid_at']);

        return $rows
            ->groupBy('client_name')
            ->map(function ($clientRows, $clientName) {
                $days = collect($clientRows)->map(function ($row) {
                    return Carbon::parse($row->delivered_at)->diffInDays(Carbon::parse($row->paid_at));
                });

                return [
                    'client_name' => $clientName,
                    'avg_days_to_pay' => round($days->avg() ?? 0, 1),
                    'paid_orders_count' => $days->count(),
                ];
            })
            ->sortByDesc('avg_days_to_pay')
            ->take($limit)
            ->values()
            ->all();
    }

    private function overdueOrders(int $tenantId, int $limit = 5): array
    {
        $installmentRows = DB::table('order_installments')
            ->join('orders', 'orders.id', '=', 'order_installments.order_id')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', false)
            ->whereDate('order_installments.due_date', '<', now()->toDateString())
            ->select(
                'orders.uuid as order_uuid',
                'clients.name as client_name',
                'order_installments.amount as amount',
                'order_installments.due_date as due_date'
            )
            ->get()
            ->map(fn($row) => [
                'order_uuid' => $row->order_uuid,
                'client_name' => $row->client_name,
                'amount' => $this->formatMoney((float) $row->amount),
                'due_date' => Carbon::parse($row->due_date)->toDateString(),
                'days_overdue' => Carbon::parse($row->due_date)->diffInDays(now()),
                'source' => 'installment',
            ]);

        $orderRows = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->where('orders.is_installment', false)
            ->where('orders.is_paid', false)
            ->whereNotNull('orders.due_date')
            ->whereDate('orders.due_date', '<', now()->toDateString())
            ->select(
                'orders.uuid as order_uuid',
                'clients.name as client_name',
                'orders.total_amount as amount',
                'orders.due_date as due_date'
            )
            ->get()
            ->map(fn($row) => [
                'order_uuid' => $row->order_uuid,
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

        $rows = DB::table('order_installments')
            ->join('orders', 'orders.id', '=', 'order_installments.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', false)
            ->get(['order_installments.amount', 'order_installments.due_date'])
            ->map(fn($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            ->concat(
                DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNull('cancelled_at')
                    ->where('is_installment', false)
                    ->where('is_paid', false)
                    ->whereNotNull('due_date')
                    ->get(['total_amount as amount', 'due_date'])
                    ->map(fn($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            );

        foreach ($rows as $row) {
            if (!$row['due_date']) {
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

        return collect($buckets)->map(fn($bucket) => [
            ...$bucket,
            'amount' => $this->formatMoney($bucket['amount']),
            'count' => (int) $bucket['count'],
        ])->values()->all();
    }

    private function receivablesForecastByMonth(int $tenantId): array
    {
        $grouped = [];

        $rows = DB::table('order_installments')
            ->join('orders', 'orders.id', '=', 'order_installments.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', false)
            ->get(['order_installments.amount', 'order_installments.due_date'])
            ->map(fn($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            ->concat(
                DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNull('cancelled_at')
                    ->where('is_installment', false)
                    ->where('is_paid', false)
                    ->whereNotNull('due_date')
                    ->get(['total_amount as amount', 'due_date'])
                    ->map(fn($row) => ['amount' => (float) $row->amount, 'due_date' => $row->due_date])
            );

        foreach ($rows as $row) {
            if (!$row['due_date']) {
                continue;
            }

            $month = Carbon::parse($row['due_date'])->format('Y-m');

            if (!isset($grouped[$month])) {
                $grouped[$month] = ['count' => 0, 'total_amount' => 0.0];
            }

            $grouped[$month]['count']++;
            $grouped[$month]['total_amount'] += (float) $row['amount'];
        }

        ksort($grouped);

        return collect($grouped)->map(fn($data, $month) => [
            'month' => $month,
            'count' => (int) $data['count'],
            'total_amount' => $this->formatMoney($data['total_amount']),
        ])->values()->all();
    }

    private function abcProducts(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_items.deleted_at')
            ->whereNull('products.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name as product_name, SUM(order_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($row) => ['product_name' => $row->product_name, 'revenue' => (float) $row->revenue]);

        return $this->abcCurve($rows->all(), 'product_name', $limit);
    }

    private function abcClients(int $tenantId, ?string $dateFrom, ?string $dateTo, int $limit = 5): array
    {
        $rows = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('clients.deleted_at')
            ->when($dateFrom, fn($q) => $q->whereDate('orders.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('orders.created_at', '<=', $dateTo))
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('clients.name as client_name, SUM(orders.total_amount) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($row) => ['client_name' => $row->client_name, 'revenue' => (float) $row->revenue]);

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

        $overdueInstallments = DB::table('order_installments')
            ->join('orders', 'orders.id', '=', 'order_installments.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', false)
            ->whereDate('order_installments.due_date', '<', $today)
            ->count();

        $overdueOrders = DB::table('orders')
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

        $currentTotal = (float) $this->ordersQuery(
            $tenantId,
            $currentStart->toDateString(),
            $currentEnd->toDateString()
        )->sum('total_amount');

        $previousTotal = (float) $this->ordersQuery(
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
                $currentStart->format('d/m/Y') . ' a ' . $currentEnd->format('d/m/Y'),
                $previousStart->format('d/m/Y') . ' a ' . $previousEnd->format('d/m/Y'),
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

    private function rfmSegment(int $recencyDays, int $frequency, float $monetary): string
    {
        if ($recencyDays <= 30 && ($frequency >= 5 || $monetary >= 1000)) {
            return 'VIP';
        }

        if ($recencyDays <= 60 && $frequency >= 3) {
            return 'Recorrente';
        }

        if ($recencyDays <= 120) {
            return 'Em risco';
        }

        return 'Inativo';
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
     * Filtros: client_uuid, cidade_uuid, bairro_uuid, is_paid,
     * is_delivered, date_from, date_to (por created_at). Cancelado
     * sempre excluído — não há filtro para incluir cancelados neste
     * endpoint (ver contrato em routes/api.php).
     */
    private function ordersEloquentQuery(int $tenantId, array $filters): Builder
    {
        $query = Order::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->with(['client', 'stockLocation']);

        if (!empty($filters['client_uuid'])) {
            $query->whereHas('client', fn($q) => $q->where('uuid', $filters['client_uuid']));
        }

        if (!empty($filters['client_name'])) {
            $query->whereHas('client', fn($q) => $q->where('name', 'like', '%' . $filters['client_name'] . '%'));
        }

        if (!empty($filters['cidade_uuid'])) {
            $query->whereHas('client.endereco.cidade', fn($q) => $q->where('uuid', $filters['cidade_uuid']));
        }

        if (!empty($filters['bairro_uuid'])) {
            $query->whereHas('client.endereco.bairro', fn($q) => $q->where('uuid', $filters['bairro_uuid']));
        }

        if (array_key_exists('is_paid', $filters) && $filters['is_paid'] !== null) {
            $query->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_delivered', $filters) && $filters['is_delivered'] !== null) {
            $query->where('is_delivered', filter_var($filters['is_delivered'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Filtro por canal (roadmap A1.3) — drill-down do by-channel:
        // GET /reports/orders?origin=X&date_from=Y&date_to=Z.
        if (!empty($filters['origin'])) {
            $normalizedOrigin = Order::normalizeOrigin((string) $filters['origin']);

            if ($normalizedOrigin === 'staff') {
                $query->whereIn('orders.origin', ['staff', 'counter']);
            } else {
                $query->where('orders.origin', $normalizedOrigin);
            }
        }

        return $query;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * CMV real (roadmap A3.13): custo médio ponderado por produto a
     * partir das entradas de estoque (`stock_movements.type=entry`) que
     * tiverem `unit_cost` preenchido — SUM(unit_cost*quantity)/SUM(quantity).
     * Entrada sem custo informado é ignorada no cálculo, nunca tratada
     * como zero. Produto sem nenhuma entrada com custo vem com `cmv=null`
     * (não há retroatividade, decisão registrada em
     * architecture-decisions.md) — a formatação final (cmv/margin_percent/
     * has_cost_data) fica no CmvResource.
     */
    public function cmv(int $tenantId): Collection
    {
        $costSubquery = DB::table('stock_movements')
            ->select('product_id', DB::raw('SUM(unit_cost * quantity) / SUM(quantity) as weighted_cost'))
            ->where('tenant_id', $tenantId)
            ->where('type', 'entry')
            ->whereNotNull('unit_cost')
            ->whereNull('deleted_at')
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoinSub($costSubquery, 'cmv_data', 'cmv_data.product_id', '=', 'products.id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')
            ->select([
                'products.uuid as product_uuid',
                'products.name as product_name',
                'products.price as sale_price',
                'cmv_data.weighted_cost as cmv',
            ])
            ->get();
    }
}
