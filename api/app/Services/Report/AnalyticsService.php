<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Analytics de vendas (Fase 1 do roadmap) — leitura agregada de
 * Sale/SaleItem/SaleInstallment/Client, sem tabela própria.
 *
 * Regras fixas deste módulo:
 * - Pedido cancelado (cancelled_at preenchido) NUNCA conta em nenhuma
 *   agregação, igual ao ReportService.
 * - Toda query filtra tenant_id explicitamente e usa Query Builder com
 *   binding (nunca interpolação de valor vindo do request).
 * - Agregação sempre via groupBy/selectRaw no SQL, nunca somando
 *   coleção de linhas cruas em PHP — só pós-processamento de linhas já
 *   agregadas (curva ABC acumulada, rótulo RFM) acontece em PHP.
 * - Período (`from`/`to`) opcional em todos os endpoints exceto
 *   sales-history; default = últimos 12 meses, filtro por
 *   sales.created_at.
 * - Funções de data são específicas de engine (MySQL em produção,
 *   SQLite nos testes) — resolvidas por driver em métodos *Expression().
 */
class AnalyticsService
{
    /**
     * Dias sem comprar a partir dos quais um cliente com 2+ pedidos é
     * considerado "churned" (evadido) em churnClients().
     */
    private const CHURN_INACTIVITY_DAYS = 60;
    private const CHECKIN_GRANTED_RESULTS = ['valido', 'reentrada_autorizada'];
    private const CHECKIN_WARNING_RESULTS = ['ja_utilizado', 'reentrada_limite_excedido', 'reentrada_intervalo_nao_atingido'];

    /**
     * Por bucket (dia ou mês): qtd de pedidos, faturamento e ticket
     * médio, mais o período imediatamente anterior de mesma duração no
     * mesmo shape (comparativo).
     */
    public function salesSummary(int $tenantId, ?string $from, ?string $to, string $groupBy = 'month'): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $days = $fromDate->diffInDays($toDate);
        $previousTo = $fromDate->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days);

        return [
            'group_by' => $groupBy,
            'current' => $this->salesSummaryPeriod($tenantId, $fromDate, $toDate, $groupBy),
            'previous' => $this->salesSummaryPeriod($tenantId, $previousFrom, $previousTo, $groupBy),
        ];
    }

    /**
     * Top produtos por faturamento no período, via sale_items
     * (snapshot de venda), com quantidade vendida.
     */
    public function topTicketTypes(int $tenantId, ?string $from, ?string $to, int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        return $this->saleItemsQuery($tenantId, $fromDate, $toDate)
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->whereNull('ticket_types.deleted_at')
            ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
            ->selectRaw('ticket_types.uuid as ticket_type_uuid, ticket_types.name as ticket_type_name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'ticket_type_uuid' => $row->ticket_type_uuid,
                'ticket_type_name' => $row->ticket_type_name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();
    }

    /**
     * Dimensões geográficas foram removidas do produto atual junto com o
     * cadastro persistido de endereço do comprador.
     */
    public function salesByLocation(int $tenantId, ?string $from, ?string $to): array
    {
        return [
            'by_city' => [],
            'by_neighborhood' => [],
        ];
    }

    /**
     * Matriz ano × mês com TODOS os anos que têm pedido (sem filtro de
     * período — histórico completo por definição). Meses sem venda
     * entram zerados; anos em ordem decrescente.
     */
    public function salesHistory(int $tenantId): array
    {
        [$yearExpr, $monthExpr] = $this->yearMonthExpressions('sales.created_at');

        $rows = $this->salesQuery($tenantId, null, null)
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, COUNT(*) as order_count, SUM(sales.total_amount) as revenue")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->get();

        $matrix = [];

        foreach ($rows as $row) {
            $matrix[(int) $row->year][(int) $row->month] = [
                'count' => (int) $row->order_count,
                'revenue' => (float) $row->revenue,
            ];
        }

        krsort($matrix);

        return collect($matrix)->map(fn(array $months, int $year) => [
            'year' => $year,
            'months' => collect(range(1, 12))->map(function (int $month) use ($months) {
                $data = $months[$month] ?? ['count' => 0, 'revenue' => 0.0];

                return [
                    'month' => $month,
                    'count' => $data['count'],
                    'revenue' => $this->formatMoney($data['revenue']),
                ];
            })->all(),
        ])->values()->all();
    }

    /**
     * Top clientes por valor total no período, com nº de pedidos,
     * última compra e rótulo RFM simples.
     *
     * Regra do rótulo (tercis calculados sobre TODOS os clientes com
     * pedido ativo no período, não só o top N):
     * - R (recência), F (frequência) e M (monetário) recebem score
     *   1..3 pelo tercil da distribuição (3 = melhor: comprou mais
     *   recentemente / mais vezes / gastou mais).
     * - R = 1 (tercil menos recente): `inativo` se F = 1, senão
     *   `em_risco` (bom cliente esfriando).
     * - R >= 2: `vip` se F = 3 e M = 3; `recorrente` se F >= 2;
     *   senão `em_risco` (compra recente porém esporádica).
     */
    public function topClients(int $tenantId, ?string $from, ?string $to, int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $rows = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
                        ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
            ->selectRaw('final_customers.uuid as client_uuid, final_customers.name as client_name, COUNT(sales.id) as order_count, SUM(sales.total_amount) as total_amount, MAX(sales.created_at) as last_order_at')
            ->orderByDesc('total_amount')
            ->get();

        $clients = $rows->map(fn($row) => [
            'client_uuid' => $row->client_uuid,
            'client_name' => $row->client_name,
            'order_count' => (int) $row->order_count,
            'total_amount' => (float) $row->total_amount,
            'last_order_at' => Carbon::parse($row->last_order_at)->toDateString(),
            'recency_days' => (int) Carbon::parse($row->last_order_at)->startOfDay()->diffInDays(now()->startOfDay()),
        ]);

        $recencyScore = $this->tercileScorer($clients->map(fn($c) => -$c['recency_days'])->all());
        $frequencyScore = $this->tercileScorer($clients->map(fn($c) => (float) $c['order_count'])->all());
        $monetaryScore = $this->tercileScorer($clients->map(fn($c) => $c['total_amount'])->all());

        return $clients
            ->map(function (array $client) use ($recencyScore, $frequencyScore, $monetaryScore) {
                $r = $recencyScore(-$client['recency_days']);
                $f = $frequencyScore((float) $client['order_count']);
                $m = $monetaryScore($client['total_amount']);

                return [
                    'client_uuid' => $client['client_uuid'],
                    'client_name' => $client['client_name'],
                    'order_count' => $client['order_count'],
                    'total_amount' => $this->formatMoney($client['total_amount']),
                    'last_order_at' => $client['last_order_at'],
                    'rfm_label' => $this->rfmLabel($r, $f, $m),
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Média de dias entre criação (created_at) e pagamento (paid_at) por
     * cliente — só pedidos pagos — ordenado do mais lento pro mais rápido.
     */
    public function paymentDelays(int $tenantId, ?string $from, ?string $to, int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $avgDaysExpr = $this->dateDiffDaysExpression('sales.paid_at', 'sales.created_at');

        return $this->salesQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->whereNotNull('sales.paid_at')
            ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
            ->selectRaw("final_customers.uuid as client_uuid, final_customers.name as client_name, AVG({$avgDaysExpr}) as avg_days_to_pay, COUNT(sales.id) as order_count")
            ->orderByDesc('avg_days_to_pay')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'client_uuid' => $row->client_uuid,
                'client_name' => $row->client_name,
                'avg_days_to_pay' => round((float) $row->avg_days_to_pay, 1),
                'order_count' => (int) $row->order_count,
            ])
            ->all();
    }

    /**
     * Pedidos em atraso de pagamento, paginado, ordenado por dias de
     * atraso desc: parcela vencida não paga (agregado por pedido: valor
     * em aberto = soma das parcelas vencidas não pagas, dias = maior
     * atraso) OU pedido não parcelado com due_date vencido e não pago
     * (valor em aberto = total_amount).
     */
    public function overdueOrders(int $tenantId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);
        $today = now()->toDateString();

        $overdueInstallments = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->join('sale_installments', 'sale_installments.sale_id', '=', 'sales.id')
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->whereNull('sale_installments.deleted_at')
            ->where('sale_installments.is_paid', false)
            ->whereDate('sale_installments.due_date', '<', $today)
            ->groupBy('sales.id', 'sales.uuid', 'final_customers.name')
            ->selectRaw(
                "sales.uuid as sale_uuid, final_customers.name as client_name, 'pagamento' as type, SUM(sale_installments.amount) as open_amount, MAX("
                . $this->daysSinceExpression('sale_installments.due_date')
                . ') as days_overdue',
                [$today]
            );

        $overduePaymentOrders = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->where('sales.is_installment', false)
            ->where('sales.is_paid', false)
            ->whereNotNull('sales.due_date')
            ->whereDate('sales.due_date', '<', $today)
            ->selectRaw(
                "sales.uuid as sale_uuid, final_customers.name as client_name, 'pagamento' as type, sales.total_amount as open_amount, "
                . $this->daysSinceExpression('sales.due_date')
                . ' as days_overdue',
                [$today]
            );

        $union = $overdueInstallments
            ->unionAll($overduePaymentOrders);

        return DB::query()
            ->fromSub($union, 'overdue')
            ->orderByDesc('days_overdue')
            ->orderBy('sale_uuid')
            ->paginate($perPage)
            ->through(fn($row) => [
                'sale_uuid' => $row->sale_uuid,
                'client_name' => $row->client_name,
                'type' => $row->type,
                'open_amount' => $this->formatMoney((float) $row->open_amount),
                'days_overdue' => (int) $row->days_overdue,
            ]);
    }

    /**
     * Curva ABC por participação acumulada no faturamento:
     * A até 80%, B de 80% (exclusive) a 95%, C o resto.
     */
    public function abcAnalysis(int $tenantId, ?string $from, ?string $to, string $dimension = 'ticket_types'): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        if ($dimension === 'clients') {
            $rows = $this->salesQuery($tenantId, $fromDate, $toDate)
                ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
                                ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
                ->selectRaw('final_customers.uuid as uuid, final_customers.name as name, SUM(sales.total_amount) as revenue')
                ->orderByDesc('revenue')
                ->get();
        } else {
            $rows = $this->saleItemsQuery($tenantId, $fromDate, $toDate)
                ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
                ->whereNull('ticket_types.deleted_at')
                ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
                ->selectRaw('ticket_types.uuid as uuid, ticket_types.name as name, SUM(sale_items.line_total) as revenue')
                ->orderByDesc('revenue')
                ->get();
        }

        $totalRevenue = (float) $rows->sum('revenue');
        $cumulative = 0.0;

        $items = $rows->map(function ($row) use ($totalRevenue, &$cumulative) {
            $revenue = (float) $row->revenue;
            $participation = $totalRevenue > 0 ? round(($revenue / $totalRevenue) * 100, 2) : 0.0;
            $cumulative = round($cumulative + $participation, 2);

            return [
                'uuid' => $row->uuid,
                'name' => $row->name,
                'revenue' => $this->formatMoney($revenue),
                'participation_percentage' => $participation,
                'cumulative_percentage' => $cumulative,
                'curve_class' => match (true) {
                    $cumulative <= 80 => 'A',
                    $cumulative <= 95 => 'B',
                    default => 'C',
                },
            ];
        })->all();

        return [
            'dimension' => $dimension,
            'total_revenue' => $this->formatMoney($totalRevenue),
            'items' => $items,
        ];
    }

    /**
     * Margem bruta do período a partir de sale_items × custo de compra
     * do produto (last_purchase_cost). Só entra no cálculo de custo/margem
     * o item cujo produto tem custo conhecido; coverage_percentage revela
     * quanto do faturamento tem custo cadastrado (qualidade do dado).
     */
    public function marginSummary(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $row = $this->saleItemsQuery($tenantId, $fromDate, $toDate)
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->selectRaw(
                'SUM(sale_items.line_total) as total_revenue, '
                . 'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN sale_items.line_total ELSE 0 END) as revenue_with_cost, '
                . 'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN sale_items.quantity * ticket_types.last_purchase_cost ELSE 0 END) as total_cost'
            )
            ->first();

        $totalRevenue = (float) ($row->total_revenue ?? 0);
        $revenueWithCost = (float) ($row->revenue_with_cost ?? 0);
        $totalCost = (float) ($row->total_cost ?? 0);
        $grossMargin = $revenueWithCost - $totalCost;

        return [
            'total_revenue' => $this->formatMoney($totalRevenue),
            'total_revenue_with_known_cost' => $this->formatMoney($revenueWithCost),
            'total_cost' => $this->formatMoney($totalCost),
            'gross_margin_amount' => $this->formatMoney($grossMargin),
            'gross_margin_percentage' => $revenueWithCost > 0 ? round(($grossMargin / $revenueWithCost) * 100, 2) : 0.0,
            'coverage_percentage' => $totalRevenue > 0 ? round(($revenueWithCost / $totalRevenue) * 100, 2) : 0.0,
        ];
    }

    /**
     * Comparação de ticket médio de pedidos COM cupom vs SEM cupom no
     * período, mais o desconto total concedido.
     *
     * ATENÇÃO: isto é uma comparação descritiva de ticket médio, NÃO uma
     * medição causal de retorno sobre investimento — pedidos com cupom
     * podem ter ticket maior por autosseleção (clientes que já comprariam
     * mais), não por efeito do cupom. Nunca rotular como "ROI real" na UI.
     */
    public function couponRoi(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $row = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->selectRaw(
                'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN 1 ELSE 0 END) as with_count, '
                . 'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN sales.total_amount ELSE 0 END) as with_total, '
                . 'SUM(CASE WHEN sales.coupon_id IS NULL THEN 1 ELSE 0 END) as without_count, '
                . 'SUM(CASE WHEN sales.coupon_id IS NULL THEN sales.total_amount ELSE 0 END) as without_total, '
                . 'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN sales.discount_amount ELSE 0 END) as total_discount'
            )
            ->first();

        $withCount = (int) ($row->with_count ?? 0);
        $withTotal = (float) ($row->with_total ?? 0);
        $withoutCount = (int) ($row->without_count ?? 0);
        $withoutTotal = (float) ($row->without_total ?? 0);
        $totalDiscount = (float) ($row->total_discount ?? 0);

        $avgWith = $withCount > 0 ? $withTotal / $withCount : 0.0;
        $avgWithout = $withoutCount > 0 ? $withoutTotal / $withoutCount : 0.0;

        return [
            'sales_with_coupon' => [
                'count' => $withCount,
                'total_amount' => $this->formatMoney($withTotal),
                'average_ticket' => $this->formatMoney($avgWith),
            ],
            'sales_without_coupon' => [
                'count' => $withoutCount,
                'total_amount' => $this->formatMoney($withoutTotal),
                'average_ticket' => $this->formatMoney($avgWithout),
            ],
            'total_discount_amount' => $this->formatMoney($totalDiscount),
            'ticket_lift_percentage' => $avgWithout > 0 ? round((($avgWith - $avgWithout) / $avgWithout) * 100, 2) : 0.0,
        ];
    }

    /**
     * Concentração de faturamento nos 10 maiores clientes do período
     * (risco de dependência de poucos compradores).
     */
    public function revenueConcentration(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $totalRevenue = (float) $this->salesQuery($tenantId, $fromDate, $toDate)->sum('sales.total_amount');

        $top10Revenue = (float) $this->salesQuery($tenantId, $fromDate, $toDate)
            ->whereNotNull('sales.final_customer_id')
            ->groupBy('sales.final_customer_id')
            ->selectRaw('SUM(sales.total_amount) as client_revenue')
            ->orderByDesc('client_revenue')
            ->limit(10)
            ->get()
            ->sum('client_revenue');

        return [
            'total_revenue' => $this->formatMoney($totalRevenue),
            'top10_revenue' => $this->formatMoney($top10Revenue),
            'concentration_percentage' => $totalRevenue > 0 ? round(($top10Revenue / $totalRevenue) * 100, 2) : 0.0,
        ];
    }

    /**
     * Clientes evadidos (churn): identificados por sales.final_customer_id, com
     * 2+ pedidos ativos e cujo último pedido é anterior a
     * CHURN_INACTIVITY_DAYS. Receita mensal em risco por cliente = soma
     * dos pedidos nos 90 dias que antecedem o último pedido, dividida por
     * 3 (média mensal daquela janela).
     */
    public function churnClients(int $tenantId): array
    {
        $threshold = now()->subDays(self::CHURN_INACTIVITY_DAYS)->startOfDay();

        $clients = $this->salesQuery($tenantId, null, null)
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
                        ->groupBy('final_customers.id', 'final_customers.name')
            ->havingRaw('COUNT(sales.id) >= 2')
            ->selectRaw('final_customers.id as client_id, final_customers.name as client_name, MAX(sales.created_at) as last_order_at')
            ->get()
            ->filter(fn($row) => Carbon::parse($row->last_order_at)->lt($threshold))
            ->keyBy(fn($row) => (int) $row->client_id);

        if ($clients->isEmpty()) {
            return [
                'churned_clients_count' => 0,
                'estimated_monthly_revenue_at_risk' => $this->formatMoney(0.0),
                'top_clients' => [],
            ];
        }

        $windows = $clients->map(fn($row) => [
            'last' => Carbon::parse($row->last_order_at),
            'start' => Carbon::parse($row->last_order_at)->subDays(90),
        ]);

        $sales = $this->salesQuery($tenantId, null, null)
            ->whereIn('sales.final_customer_id', $clients->keys()->all())
            ->get(['sales.final_customer_id as client_id', 'sales.created_at', 'sales.total_amount']);

        $revenueAtRisk = [];

        foreach ($sales as $order) {
            $clientId = (int) $order->client_id;
            $window = $windows[$clientId];
            $createdAt = Carbon::parse($order->created_at);

            if ($createdAt->gte($window['start']) && $createdAt->lte($window['last'])) {
                $revenueAtRisk[$clientId] = ($revenueAtRisk[$clientId] ?? 0.0) + (float) $order->total_amount;
            }
        }

        $rows = $clients->map(function ($row) use ($revenueAtRisk) {
            $clientId = (int) $row->client_id;

            return [
                'client_name' => $row->client_name,
                'last_order_at' => Carbon::parse($row->last_order_at)->toDateString(),
                'monthly_revenue_at_risk' => round(($revenueAtRisk[$clientId] ?? 0.0) / 3, 2),
            ];
        })->values();

        $topClients = $rows->sortByDesc('monthly_revenue_at_risk')
            ->take(10)
            ->map(fn($item) => [
                'client_name' => $item['client_name'],
                'last_order_at' => $item['last_order_at'],
                'monthly_revenue_at_risk' => $this->formatMoney($item['monthly_revenue_at_risk']),
            ])
            ->values()
            ->all();

        return [
            'churned_clients_count' => $clients->count(),
            'estimated_monthly_revenue_at_risk' => $this->formatMoney((float) $rows->sum('monthly_revenue_at_risk')),
            'top_clients' => $topClients,
        ];
    }

    /**
     * Mapa de calor de vendas por dia da semana × hora do dia no período.
     * Retorna só as células com pedido (frontend completa a grade 7×24 com
     * zero). day_of_week 1..7 (padrão MySQL DAYOFWEEK: 1=domingo), hour
     * 0..23.
     */
    public function salesByHour(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $dowExpr = $this->dayOfWeekExpression('sales.created_at');
        $hourExpr = $this->hourExpression('sales.created_at');

        $cells = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->selectRaw("{$dowExpr} as day_of_week, {$hourExpr} as hour, COUNT(*) as order_count, SUM(sales.total_amount) as revenue")
            ->groupByRaw("{$dowExpr}, {$hourExpr}")
            ->orderByRaw("{$dowExpr}, {$hourExpr}")
            ->get()
            ->map(fn($row) => [
                'day_of_week' => (int) $row->day_of_week,
                'hour' => (int) $row->hour,
                'count' => (int) $row->order_count,
                'total_amount' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();

        return ['cells' => $cells];
    }

    public function checkinInsights(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $grantedCase = $this->caseWhenIn('ticket_checkins.result', self::CHECKIN_GRANTED_RESULTS);
        $warningCase = $this->caseWhenIn('ticket_checkins.result', self::CHECKIN_WARNING_RESULTS);
        $blockedCase = "CASE WHEN {$grantedCase} = 0 AND {$warningCase} = 0 THEN 1 ELSE 0 END";

        $baseQuery = $this->ticketCheckinsQuery($tenantId, $fromDate, $toDate);

        $totals = (clone $baseQuery)
            ->selectRaw(
                "COUNT(ticket_checkins.id) as total_reads,
                SUM({$grantedCase}) as granted_reads,
                SUM({$warningCase}) as warning_reads,
                SUM({$blockedCase}) as blocked_reads,
                SUM(CASE WHEN ticket_checkins.result = 'reentrada_autorizada' THEN 1 ELSE 0 END) as reentries,
                COUNT(DISTINCT CASE WHEN {$grantedCase} = 1 THEN ticket_checkins.ticket_id END) as unique_granted_tickets"
            )
            ->first();

        $issuedBySession = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->leftJoin('event_sessions', 'event_sessions.id', '=', 'ticket_types.event_session_id')
            ->where('tickets.tenant_id', $tenantId)
            ->whereNull('tickets.deleted_at')
            ->selectRaw('event_sessions.uuid as session_uuid, COUNT(tickets.id) as issued_count')
            ->groupBy('event_sessions.uuid')
            ->pluck('issued_count', 'session_uuid')
            ->map(fn ($count) => (int) $count)
            ->all();

        $issuedByTicketType = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where('tickets.tenant_id', $tenantId)
            ->whereNull('tickets.deleted_at')
            ->selectRaw('ticket_types.uuid as ticket_type_uuid, COUNT(tickets.id) as issued_count')
            ->groupBy('ticket_types.uuid')
            ->pluck('issued_count', 'ticket_type_uuid')
            ->map(fn ($count) => (int) $count)
            ->all();

        $bySession = (clone $baseQuery)
            ->selectRaw(
                "event_sessions.uuid as session_uuid,
                COALESCE(event_sessions.name, 'Sem sessão') as session_name,
                events.uuid as event_uuid,
                events.name as event_name,
                COUNT(ticket_checkins.id) as total_reads,
                SUM({$grantedCase}) as granted_reads,
                SUM({$warningCase}) as warning_reads,
                SUM({$blockedCase}) as blocked_reads,
                COUNT(DISTINCT CASE WHEN {$grantedCase} = 1 THEN ticket_checkins.ticket_id END) as unique_granted_tickets"
            )
            ->groupBy('event_sessions.uuid', 'event_sessions.name', 'events.uuid', 'events.name')
            ->orderByDesc('granted_reads')
            ->orderByDesc('total_reads')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($issuedBySession) {
                $sessionUuid = $row->session_uuid;
                $issuedCount = $issuedBySession[$sessionUuid] ?? 0;

                return [
                    'session_uuid' => $sessionUuid,
                    'session_name' => $row->session_name,
                    'event_uuid' => $row->event_uuid,
                    'event_name' => $row->event_name,
                    'total_reads' => (int) $row->total_reads,
                    'granted_reads' => (int) $row->granted_reads,
                    'warning_reads' => (int) $row->warning_reads,
                    'blocked_reads' => (int) $row->blocked_reads,
                    'unique_granted_tickets' => (int) $row->unique_granted_tickets,
                    'attendance_rate' => $this->percentage((int) $row->unique_granted_tickets, $issuedCount),
                ];
            })
            ->all();

        $byTicketType = (clone $baseQuery)
            ->selectRaw(
                "ticket_types.uuid as ticket_type_uuid,
                ticket_types.name as ticket_type_name,
                events.uuid as event_uuid,
                events.name as event_name,
                COUNT(ticket_checkins.id) as total_reads,
                SUM({$grantedCase}) as granted_reads,
                SUM({$warningCase}) as warning_reads,
                SUM({$blockedCase}) as blocked_reads,
                COUNT(DISTINCT CASE WHEN {$grantedCase} = 1 THEN ticket_checkins.ticket_id END) as unique_granted_tickets"
            )
            ->groupBy('ticket_types.uuid', 'ticket_types.name', 'events.uuid', 'events.name')
            ->orderByDesc('granted_reads')
            ->orderByDesc('total_reads')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($issuedByTicketType) {
                $issuedCount = $issuedByTicketType[$row->ticket_type_uuid] ?? 0;

                return [
                    'ticket_type_uuid' => $row->ticket_type_uuid,
                    'ticket_type_name' => $row->ticket_type_name,
                    'event_uuid' => $row->event_uuid,
                    'event_name' => $row->event_name,
                    'total_reads' => (int) $row->total_reads,
                    'granted_reads' => (int) $row->granted_reads,
                    'warning_reads' => (int) $row->warning_reads,
                    'blocked_reads' => (int) $row->blocked_reads,
                    'unique_granted_tickets' => (int) $row->unique_granted_tickets,
                    'attendance_rate' => $this->percentage((int) $row->unique_granted_tickets, $issuedCount),
                ];
            })
            ->all();

        return [
            'totals' => [
                'total_reads' => (int) ($totals->total_reads ?? 0),
                'granted_reads' => (int) ($totals->granted_reads ?? 0),
                'warning_reads' => (int) ($totals->warning_reads ?? 0),
                'blocked_reads' => (int) ($totals->blocked_reads ?? 0),
                'reentries' => (int) ($totals->reentries ?? 0),
                'unique_granted_tickets' => (int) ($totals->unique_granted_tickets ?? 0),
                'attendance_rate' => $this->percentage(
                    (int) ($totals->unique_granted_tickets ?? 0),
                    array_sum($issuedByTicketType)
                ),
            ],
            'by_session' => $bySession,
            'by_ticket_type' => $byTicketType,
        ];
    }

    // ------------------------------------------------------------------
    // Bases de query
    // ------------------------------------------------------------------

    private function salesQuery(int $tenantId, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        return DB::table('sales')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->when($from, fn($q) => $q->whereDate('sales.created_at', '>=', $from->toDateString()))
            ->when($to, fn($q) => $q->whereDate('sales.created_at', '<=', $to->toDateString()));
    }

    private function saleItemsQuery(int $tenantId, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->when($from, fn($q) => $q->whereDate('sales.created_at', '>=', $from->toDateString()))
            ->when($to, fn($q) => $q->whereDate('sales.created_at', '<=', $to->toDateString()));
    }

    private function ticketCheckinsQuery(int $tenantId, Carbon $from, Carbon $to): QueryBuilder
    {
        return DB::table('ticket_checkins')
            ->join('tickets', 'tickets.id', '=', 'ticket_checkins.ticket_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->leftJoin('event_sessions', 'event_sessions.id', '=', 'ticket_types.event_session_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->where('ticket_checkins.tenant_id', $tenantId)
            ->whereNull('ticket_checkins.deleted_at')
            ->whereBetween('ticket_checkins.checked_in_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ]);
    }

    private function salesSummaryPeriod(int $tenantId, Carbon $from, Carbon $to, string $groupBy): array
    {
        $bucketExpr = $this->bucketExpression($groupBy);

        $buckets = $this->salesQuery($tenantId, $from, $to)
            ->selectRaw("{$bucketExpr} as bucket, COUNT(*) as order_count, SUM(sales.total_amount) as revenue")
            ->groupByRaw($bucketExpr)
            ->orderByRaw($bucketExpr)
            ->get()
            ->map(fn($row) => [
                'bucket' => $row->bucket,
                'order_count' => (int) $row->order_count,
                'revenue' => $this->formatMoney((float) $row->revenue),
                'average_ticket' => $this->formatMoney((int) $row->order_count > 0 ? ((float) $row->revenue) / (int) $row->order_count : 0.0),
            ])
            ->all();

        $totalOrders = array_sum(array_column($buckets, 'order_count'));
        $totalRevenue = array_sum(array_map('floatval', array_column($buckets, 'revenue')));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_sales' => $totalOrders,
            'total_revenue' => $this->formatMoney($totalRevenue),
            'average_ticket' => $this->formatMoney($totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0),
            'buckets' => $buckets,
        ];
    }

    // ------------------------------------------------------------------
    // Expressões de data por driver (MySQL em produção, SQLite em teste)
    // ------------------------------------------------------------------

    private function bucketExpression(string $groupBy): string
    {
        $format = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%m';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', sales.created_at)"
            : "DATE_FORMAT(sales.created_at, '{$format}')";
    }

    /**
     * @return array{0: string, 1: string} [yearExpr, monthExpr]
     */
    private function yearMonthExpressions(string $column): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return [
                "CAST(strftime('%Y', {$column}) AS INTEGER)",
                "CAST(strftime('%m', {$column}) AS INTEGER)",
            ];
        }

        return ["YEAR({$column})", "MONTH({$column})"];
    }

    /**
     * Diferença em dias inteiros entre dois timestamps (a - b).
     */
    private function dateDiffDaysExpression(string $a, string $b): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday(date({$a})) - julianday(date({$b})))"
            : "DATEDIFF({$a}, {$b})";
    }

    /**
     * Dias decorridos entre uma coluna de data e a data passada como
     * binding (hoje) — usar com selectRaw(..., [$today]).
     */
    private function daysSinceExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday(?) - julianday(date({$column})) AS INTEGER)"
            : "DATEDIFF(?, {$column})";
    }

    /**
     * Dia da semana 1..7 (padrão MySQL DAYOFWEEK: 1=domingo, 7=sábado).
     * No SQLite strftime('%w') dá 0..6 (0=domingo), então soma 1.
     */
    private function dayOfWeekExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(CAST(strftime('%w', {$column}) AS INTEGER) + 1)"
            : "DAYOFWEEK({$column})";
    }

    /**
     * Hora do dia 0..23.
     */
    private function hourExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$column}) AS INTEGER)"
            : "HOUR({$column})";
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        $toDate = $to ? Carbon::parse($to)->startOfDay() : now()->startOfDay();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : $toDate->copy()->subMonths(12);

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    /**
     * Retorna um closure valor→score 1..3 pelos tercis da distribuição
     * (valores maiores = score maior). Com menos de 3 valores todo
     * mundo cai no melhor tercil disponível — suficiente pro rótulo RFM
     * simples desta fase.
     */
    private function tercileScorer(array $values): \Closure
    {
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if ($count === 0) {
            return fn(float $value): int => 1;
        }

        $q1 = $sorted[(int) floor($count / 3)] ?? $sorted[0];
        $q2 = $sorted[(int) floor(($count * 2) / 3)] ?? $sorted[$count - 1];

        return fn(float $value): int => match (true) {
            $value >= $q2 => 3,
            $value >= $q1 => 2,
            default => 1,
        };
    }

    private function rfmLabel(int $r, int $f, int $m): string
    {
        if ($r === 1) {
            return $f === 1 ? 'inativo' : 'em_risco';
        }

        if ($f === 3 && $m === 3) {
            return 'vip';
        }

        return $f >= 2 ? 'recorrente' : 'em_risco';
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function caseWhenIn(string $column, array $values): string
    {
        $quoted = implode(', ', array_map(
            fn(string $value) => DB::getPdo()->quote($value),
            $values
        ));

        return "CASE WHEN {$column} IN ({$quoted}) THEN 1 ELSE 0 END";
    }

    private function percentage(int $value, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(($value / $base) * 100, 2);
    }
}
