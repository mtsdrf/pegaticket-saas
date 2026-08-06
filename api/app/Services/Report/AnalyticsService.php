<?php

namespace App\Services\Report;

use App\Models\Event\Event;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analytics de vendas (Fase 1 do roadmap) — leitura agregada de
 * Sale/SaleItem/SaleInstallment/Client, sem tabela própria.
 *
 * Regras fixas deste módulo:
 * - Venda cancelado (cancelled_at preenchido) NUNCA conta em nenhuma
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
     * Dias sem comprar a partir dos quais um cliente com 2+ vendas é
     * considerado "churned" (evadido) em churnClients().
     */
    private const CHURN_INACTIVITY_DAYS = 60;

    /**
     * Heurística de sinal de abuso de cupom (roadmap Fase A2) — uso mínimo
     * e média de uso por cliente distinto a partir dos quais o cupom é
     * sinalizado para revisão manual (ver `couponsReport()`).
     */
    private const COUPON_ABUSE_MIN_USAGE = 5;

    private const COUPON_ABUSE_AVG_USES_PER_CUSTOMER = 2.0;

    private const CHECKIN_GRANTED_RESULTS = ['valido', 'reentrada_autorizada'];

    private const CHECKIN_WARNING_RESULTS = ['ja_utilizado', 'reentrada_limite_excedido', 'reentrada_intervalo_nao_atingido'];

    /**
     * Meses após a coorte de aquisição cobertos por cohortsReport() (M0..M6)
     * — roadmap Fase A3 parte 2, decisão de escopo explícita para não virar
     * full-scan sem limite conforme o histórico do tenant cresce.
     */
    private const COHORT_MAX_MONTHS = 6;

    public function __construct(
        private readonly RfmCalculator $rfmCalculator = new RfmCalculator
    ) {}

    /**
     * Por bucket (dia ou mês): qtd de vendas, faturamento e ticket
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
            ->map(fn ($row) => [
                'ticket_type_uuid' => $row->ticket_type_uuid,
                'ticket_type_name' => $row->ticket_type_name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();
    }

    /**
     * Matriz ano × mês com TODOS os anos que têm venda (sem filtro de
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

        return collect($matrix)->map(fn (array $months, int $year) => [
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
     * Top clientes por valor total no período, com nº de vendas, última
     * compra e rótulo RFM. Segmento calculado por `RfmCalculator`
     * (tercis sobre TODOS os clientes com venda ativa no período, não só
     * o top N) — mesma fórmula usada por `ReportService::rfmClients()`,
     * ver `RfmCalculator` para a regra completa.
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

        $clients = $rows->map(fn ($row) => [
            'client_uuid' => $row->client_uuid,
            'client_name' => $row->client_name,
            'order_count' => (int) $row->order_count,
            'total_amount' => (float) $row->total_amount,
            'last_order_at' => Carbon::parse($row->last_order_at)->toDateString(),
            'recency_days' => (int) Carbon::parse($row->last_order_at)->startOfDay()->diffInDays(now()->startOfDay()),
        ]);

        $rfmInput = $clients->map(fn (array $c) => [
            'recency_days' => $c['recency_days'],
            'frequency' => $c['order_count'],
            'monetary' => $c['total_amount'],
        ])->all();
        $segments = $this->rfmCalculator->segments($rfmInput);
        $segments8 = $this->rfmCalculator->segments8($rfmInput);

        return $clients
            ->values()
            ->map(fn (array $client, int $index) => [
                'client_uuid' => $client['client_uuid'],
                'client_name' => $client['client_name'],
                'order_count' => $client['order_count'],
                'total_amount' => $this->formatMoney($client['total_amount']),
                'last_order_at' => $client['last_order_at'],
                'rfm_label' => $segments[$index],
                'rfm_segment8' => $segments8[$index],
                'rfm_segment8_label' => $this->rfmCalculator->displaySegment8Label($segments8[$index]),
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Curva ABC por participação acumulada no faturamento:
     * A até 80%, B de 80% (exclusive) a 95%, C o resto.
     */
    public function abcAnalysis(int $tenantId, ?string $from, ?string $to, string $dimension = 'ticket_types'): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $rows = $this->saleItemsQuery($tenantId, $fromDate, $toDate)
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->whereNull('ticket_types.deleted_at')
            ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
            ->selectRaw('ticket_types.uuid as uuid, ticket_types.name as name, SUM(sale_items.line_total) as revenue')
            ->orderByDesc('revenue')
            ->get();

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
                .'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN sale_items.line_total ELSE 0 END) as revenue_with_cost, '
                .'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN sale_items.quantity * ticket_types.last_purchase_cost ELSE 0 END) as total_cost'
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
     * Comparação de ticket médio de vendas COM cupom vs SEM cupom no
     * período, mais o desconto total concedido.
     *
     * ATENÇÃO: isto é uma comparação descritiva de ticket médio, NÃO uma
     * medição causal de retorno sobre investimento — vendas com cupom
     * podem ter ticket maior por autosseleção (clientes que já comprariam
     * mais), não por efeito do cupom. Nunca rotular como "ROI real" na UI.
     */
    public function couponRoi(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $row = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->selectRaw(
                'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN 1 ELSE 0 END) as with_count, '
                .'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN sales.total_amount ELSE 0 END) as with_total, '
                .'SUM(CASE WHEN sales.coupon_id IS NULL THEN 1 ELSE 0 END) as without_count, '
                .'SUM(CASE WHEN sales.coupon_id IS NULL THEN sales.total_amount ELSE 0 END) as without_total, '
                .'SUM(CASE WHEN sales.coupon_id IS NOT NULL THEN sales.discount_amount ELSE 0 END) as total_discount'
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
     * 2+ vendas ativos e cujo última venda é anterior a
     * CHURN_INACTIVITY_DAYS. Receita mensal em risco por cliente = soma
     * dos vendas nos 90 dias que antecedem o última venda, dividida por
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
            ->filter(fn ($row) => Carbon::parse($row->last_order_at)->lt($threshold))
            ->keyBy(fn ($row) => (int) $row->client_id);

        if ($clients->isEmpty()) {
            return [
                'churned_clients_count' => 0,
                'estimated_monthly_revenue_at_risk' => $this->formatMoney(0.0),
                'top_clients' => [],
            ];
        }

        $windows = $clients->map(fn ($row) => [
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
            ->map(fn ($item) => [
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
     * Retorna só as células com venda (frontend completa a grade 7×24 com
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
            ->map(fn ($row) => [
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

    /**
     * Relatório de vendas por dimensão configurável (roadmap Fase A1) —
     * unifica `topTicketTypes()`/`topClients()`/`ReportService::byChannel()`
     * num único endpoint aditivo (os três antigos continuam funcionando,
     * consumidos hoje pelo frontend; nenhum contrato quebrado). Shape de
     * item uniforme entre dimensões: `key`/`label` sempre presentes,
     * `quantity_sold` só é populado para `ticket_type` (as demais dimensões
     * não têm "quantidade de item", só contagem de pedido).
     *
     * @return array{dimension: string, items: list<array{key: string, label: string, order_count: int|null, quantity_sold: float|null, revenue: string}>}
     */
    public function salesByDimension(int $tenantId, ?string $from, ?string $to, string $dimension = 'ticket_type', int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $items = match ($dimension) {
            'client' => $this->salesQuery($tenantId, $fromDate, $toDate)
                ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
                ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
                ->selectRaw('final_customers.uuid as item_key, final_customers.name as item_label, COUNT(sales.id) as order_count, SUM(sales.total_amount) as revenue')
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'key' => $row->item_key,
                    'label' => $row->item_label,
                    'order_count' => (int) $row->order_count,
                    'quantity_sold' => null,
                    'revenue' => $this->formatMoney((float) $row->revenue),
                ])
                ->all(),
            'origin' => $this->salesQuery($tenantId, $fromDate, $toDate)
                ->selectRaw("CASE WHEN sales.origin = 'counter' THEN 'staff' ELSE sales.origin END as item_key, COUNT(*) as order_count, SUM(sales.total_amount) as revenue")
                ->groupByRaw("CASE WHEN sales.origin = 'counter' THEN 'staff' ELSE sales.origin END")
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'key' => Sale::normalizeOrigin((string) $row->item_key),
                    'label' => Sale::normalizeOrigin((string) $row->item_key),
                    'order_count' => (int) $row->order_count,
                    'quantity_sold' => null,
                    'revenue' => $this->formatMoney((float) $row->revenue),
                ])
                ->all(),
            default => $this->saleItemsQuery($tenantId, $fromDate, $toDate)
                ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
                ->whereNull('ticket_types.deleted_at')
                ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
                ->selectRaw('ticket_types.uuid as item_key, ticket_types.name as item_label, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
                ->orderByDesc('revenue')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'key' => $row->item_key,
                    'label' => $row->item_label,
                    'order_count' => null,
                    'quantity_sold' => (float) $row->quantity_sold,
                    'revenue' => $this->formatMoney((float) $row->revenue),
                ])
                ->all(),
        };

        return [
            'dimension' => in_array($dimension, ['client', 'origin', 'ticket_type'], true) ? $dimension : 'ticket_type',
            'items' => $items,
        ];
    }

    /**
     * Relatório de pagamentos básico (roadmap Fase A1) — aprovação/recusa
     * por período, sobre `sales.status` (`pending_approval`/`confirmed`/
     * `rejected`). SEM roteamento entre gateways (decisão de produto já
     * fechada — só PagBank existe). `approval_rate`/`rejection_rate` são
     * calculados só sobre vendas DECIDIDAS (confirmed + rejected); vendas
     * ainda `pending_approval` não entram no denominador dessas taxas.
     */
    public function paymentsSummary(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $rows = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->selectRaw('sales.status as status, COUNT(*) as order_count, SUM(sales.total_amount) as total_amount')
            ->groupBy('sales.status')
            ->get()
            ->keyBy('status');

        $byStatus = fn (string $status) => [
            'count' => (int) ($rows[$status]->order_count ?? 0),
            'total_amount' => $this->formatMoney((float) ($rows[$status]->total_amount ?? 0)),
        ];

        $confirmed = $byStatus('confirmed');
        $rejected = $byStatus('rejected');
        $pending = $byStatus('pending_approval');

        $decidedCount = $confirmed['count'] + $rejected['count'];

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'confirmed' => $confirmed,
            'rejected' => $rejected,
            'pending_approval' => $pending,
            'approval_rate_percentage' => $decidedCount > 0 ? round(($confirmed['count'] / $decidedCount) * 100, 2) : 0.0,
            'rejection_rate_percentage' => $decidedCount > 0 ? round(($rejected['count'] / $decidedCount) * 100, 2) : 0.0,
        ];
    }

    /**
     * Relatório de afiliados consolidado (roadmap Fase A2) — ranking por
     * comissão gerada/vendas atribuídas + ROI. Fonte: `affiliate_commissions`
     * (uma linha por venda paga com `affiliate_id`, ver `AffiliateCommissionService`),
     * período por `affiliate_commissions.created_at` (nasce junto com a
     * venda paga). `roi_percentage` = `(receita_atribuída - comissão_paga) /
     * comissão_paga * 100` — só considera comissão já `paid` como custo
     * (comissão `pending` ainda não foi um gasto realizado); `null` quando
     * não há comissão paga (divisão por zero).
     *
     * @return array{from: string, to: string, totals: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function affiliatesReport(int $tenantId, ?string $from, ?string $to, int $limit = 20): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $rows = DB::table('affiliate_commissions')
            ->join('affiliates', 'affiliates.id', '=', 'affiliate_commissions.affiliate_id')
            ->where('affiliate_commissions.tenant_id', $tenantId)
            ->whereNull('affiliate_commissions.deleted_at')
            ->whereNull('affiliates.deleted_at')
            ->whereBetween('affiliate_commissions.created_at', [$fromDate, $toDate->copy()->endOfDay()])
            ->groupBy('affiliates.id', 'affiliates.uuid', 'affiliates.name', 'affiliates.tracking_code', 'affiliates.status')
            ->selectRaw(
                "affiliates.uuid as affiliate_uuid,
                affiliates.name as affiliate_name,
                affiliates.tracking_code as tracking_code,
                affiliates.status as affiliate_status,
                COUNT(*) as attributed_sales_count,
                SUM(affiliate_commissions.sale_amount) as attributed_revenue,
                SUM(affiliate_commissions.amount) as commission_amount,
                SUM(CASE WHEN affiliate_commissions.status = 'paid' THEN affiliate_commissions.amount ELSE 0 END) as commission_paid_amount"
            )
            ->orderByDesc('attributed_revenue')
            ->limit($limit)
            ->get();

        $items = $rows->map(function ($row) {
            $attributedRevenue = (float) $row->attributed_revenue;
            $commissionPaid = (float) $row->commission_paid_amount;

            return [
                'affiliate_uuid' => $row->affiliate_uuid,
                'affiliate_name' => $row->affiliate_name,
                'tracking_code' => $row->tracking_code,
                'affiliate_status' => $row->affiliate_status,
                'attributed_sales_count' => (int) $row->attributed_sales_count,
                'attributed_revenue' => $this->formatMoney($attributedRevenue),
                'commission_amount' => $this->formatMoney((float) $row->commission_amount),
                'commission_paid_amount' => $this->formatMoney($commissionPaid),
                'roi_percentage' => $commissionPaid > 0
                    ? round((($attributedRevenue - $commissionPaid) / $commissionPaid) * 100, 2)
                    : null,
            ];
        })->all();

        $totalAttributedRevenue = array_sum(array_map(fn ($item) => (float) $item['attributed_revenue'], $items));
        $totalCommissionAmount = array_sum(array_map(fn ($item) => (float) $item['commission_amount'], $items));
        $totalCommissionPaid = array_sum(array_map(fn ($item) => (float) $item['commission_paid_amount'], $items));

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'totals' => [
                'affiliates_count' => count($items),
                'attributed_revenue' => $this->formatMoney($totalAttributedRevenue),
                'commission_amount' => $this->formatMoney($totalCommissionAmount),
                'commission_paid_amount' => $this->formatMoney($totalCommissionPaid),
                'roi_percentage' => $totalCommissionPaid > 0
                    ? round((($totalAttributedRevenue - $totalCommissionPaid) / $totalCommissionPaid) * 100, 2)
                    : null,
            ],
            'items' => $items,
        ];
    }

    /**
     * Relatório de cupons consolidado (roadmap Fase A2) — ranking de uso,
     * taxa de conversão (redemption que virou venda paga), valor
     * descontado total e sinal de abuso. Fonte: `coupon_redemptions`
     * (log de uso, um registro por venda que aplicou o cupom — removido
     * pelo sistema se a venda for recusada, ver `CouponRedemption`) join
     * `sales` (para status/valor/desconto real aplicado). Período por
     * `coupon_redemptions.redeemed_at`.
     *
     * `abuse_signal`: heurística simples e independente do `RiskEngineService`
     * (que avalia fraude por venda/cliente, não concentração de cupom) —
     * dispara quando o cupom tem uso alto (>= ABUSE_MIN_USAGE) E a média de
     * uso por cliente distinto é alta (>= ABUSE_AVG_USES_PER_CUSTOMER),
     * sinal de poucos clientes reaproveitando o mesmo cupom muitas vezes.
     * Não é decisão automática, só sinalização para revisão manual.
     *
     * @return array{from: string, to: string, totals: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function couponsReport(int $tenantId, ?string $from, ?string $to, int $limit = 20): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $rows = DB::table('coupon_redemptions')
            ->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')
            ->join('sales', 'sales.id', '=', 'coupon_redemptions.sale_id')
            ->where('coupon_redemptions.tenant_id', $tenantId)
            ->whereNull('coupons.deleted_at')
            ->whereNull('sales.deleted_at')
            ->whereBetween('coupon_redemptions.redeemed_at', [$fromDate, $toDate->copy()->endOfDay()])
            ->groupBy('coupons.id', 'coupons.uuid', 'coupons.code', 'coupons.type', 'coupons.is_active')
            ->selectRaw(
                'coupons.uuid as coupon_uuid,
                coupons.code as coupon_code,
                coupons.type as coupon_type,
                coupons.is_active as coupon_is_active,
                COUNT(*) as usage_count,
                COUNT(DISTINCT coupon_redemptions.final_customer_id) as distinct_customers_count,
                SUM(CASE WHEN sales.is_paid = 1 AND sales.cancelled_at IS NULL THEN 1 ELSE 0 END) as paid_usage_count,
                SUM(CASE WHEN sales.cancelled_at IS NULL THEN sales.discount_amount ELSE 0 END) as total_discount_amount,
                SUM(CASE WHEN sales.is_paid = 1 AND sales.cancelled_at IS NULL THEN sales.total_amount ELSE 0 END) as revenue_generated'
            )
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();

        $items = $rows->map(function ($row) {
            $usageCount = (int) $row->usage_count;
            $distinctCustomers = (int) $row->distinct_customers_count;
            $avgUsesPerCustomer = $distinctCustomers > 0 ? round($usageCount / $distinctCustomers, 2) : 0.0;

            return [
                'coupon_uuid' => $row->coupon_uuid,
                'coupon_code' => $row->coupon_code,
                'coupon_type' => $row->coupon_type,
                'coupon_is_active' => (bool) $row->coupon_is_active,
                'usage_count' => $usageCount,
                'distinct_customers_count' => $distinctCustomers,
                'paid_usage_count' => (int) $row->paid_usage_count,
                'conversion_rate_percentage' => $this->percentage((int) $row->paid_usage_count, $usageCount),
                'total_discount_amount' => $this->formatMoney((float) $row->total_discount_amount),
                'revenue_generated' => $this->formatMoney((float) $row->revenue_generated),
                'avg_uses_per_customer' => $avgUsesPerCustomer,
                'abuse_signal' => $usageCount >= self::COUPON_ABUSE_MIN_USAGE
                    && $avgUsesPerCustomer >= self::COUPON_ABUSE_AVG_USES_PER_CUSTOMER,
            ];
        })->all();

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'totals' => [
                'coupons_used_count' => count($items),
                'total_redemptions' => array_sum(array_column($items, 'usage_count')),
                'total_discount_amount' => $this->formatMoney(array_sum(array_map(fn ($item) => (float) $item['total_discount_amount'], $items))),
                'coupons_with_abuse_signal_count' => count(array_filter($items, fn ($item) => $item['abuse_signal'])),
            ],
            'items' => $items,
        ];
    }

    /**
     * Relatório de reembolsos/chargebacks dedicado (roadmap Fase A2) —
     * volume, valor, motivo (texto livre — `sale_refunds.reason`,
     * agregado por correspondência exata, melhor esforço) e taxa de
     * reembolso sobre vendas pagas no período. Período por
     * `sale_refunds.refunded_at`; a base de vendas pagas para a taxa usa
     * `sales.created_at` no mesmo período (mesma convenção de
     * `salesQuery()`), não `paid_at` — consistente com o resto do módulo.
     *
     * @return array{from: string, to: string, totals: array<string, mixed>, by_type: array<string, mixed>, top_reasons: list<array<string, mixed>>}
     */
    public function refundsReport(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $refundsQuery = DB::table('sale_refunds')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('refunded_at', [$fromDate, $toDate->copy()->endOfDay()]);

        $totals = (clone $refundsQuery)
            ->selectRaw('COUNT(*) as refunds_count, SUM(amount) as total_refunded_amount')
            ->first();

        $byType = (clone $refundsQuery)
            ->selectRaw('type, COUNT(*) as refunds_count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $typeGroup = fn (string $type) => [
            'count' => (int) ($byType[$type]->refunds_count ?? 0),
            'total_amount' => $this->formatMoney((float) ($byType[$type]->total_amount ?? 0)),
        ];

        $topReasons = (clone $refundsQuery)
            ->selectRaw('reason, COUNT(*) as refunds_count, SUM(amount) as total_amount')
            ->groupBy('reason')
            ->orderByDesc('refunds_count')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'reason' => $row->reason,
                'count' => (int) $row->refunds_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();

        $paidSales = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->where('sales.is_paid', true)
            ->selectRaw('COUNT(*) as paid_sales_count, SUM(sales.total_amount) as paid_sales_amount')
            ->first();

        $refundsCount = (int) ($totals->refunds_count ?? 0);
        $totalRefundedAmount = (float) ($totals->total_refunded_amount ?? 0);
        $paidSalesCount = (int) ($paidSales->paid_sales_count ?? 0);
        $paidSalesAmount = (float) ($paidSales->paid_sales_amount ?? 0);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'totals' => [
                'refunds_count' => $refundsCount,
                'total_refunded_amount' => $this->formatMoney($totalRefundedAmount),
                'refund_rate_percentage' => $this->percentage($refundsCount, $paidSalesCount),
                'refund_amount_rate_percentage' => $paidSalesAmount > 0
                    ? round(($totalRefundedAmount / $paidSalesAmount) * 100, 2)
                    : 0.0,
            ],
            'by_type' => [
                'total' => $typeGroup('total'),
                'parcial' => $typeGroup('parcial'),
            ],
            'top_reasons' => $topReasons,
        ];
    }

    /**
     * Relatório de inventário/assentos avançado (roadmap Fase A2) —
     * ocupação agregada por setor para um evento específico (visão de
     * staff, não disponibilidade individual de compra — isso já é coberto
     * por `StorefrontHoldService`). `event_uuid` é sempre obrigatório
     * (regra transversal de filtro ativo) — snapshot atual, sem filtro de
     * período (mesma natureza estrutural de `occupancy_percentage`/KPI 4).
     * Evento sem `venue_map_version_id` (sem mapa de assentos, só GA) volta
     * `has_seat_map = false` e `sectors = []`.
     *
     * @return array{event_uuid: string, event_name: string, has_seat_map: bool, totals: array<string, mixed>, sectors: list<array<string, mixed>>}
     */
    public function inventoryReport(int $tenantId, string $eventUuid): array
    {
        $event = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('uuid', $eventUuid)
            ->whereNull('deleted_at')
            ->first();

        if (! $event) {
            throw (new ModelNotFoundException)->setModel(Event::class);
        }

        if ($event->venue_map_version_id === null) {
            return [
                'event_uuid' => $eventUuid,
                'event_name' => $event->name,
                'has_seat_map' => false,
                'totals' => [
                    'total_seats' => 0,
                    'sold_seats' => 0,
                    'held_seats_now' => 0,
                    'blocked_seats' => 0,
                    'available_seats' => 0,
                    'occupancy_percentage' => 0.0,
                ],
                'sectors' => [],
            ];
        }

        $soldBySector = DB::table('seats')
            ->join('tickets', 'tickets.seat_id', '=', 'seats.id')
            ->where('seats.venue_map_version_id', $event->venue_map_version_id)
            ->where('tickets.tenant_id', $tenantId)
            ->where('tickets.status', 'ativo')
            ->whereNull('tickets.deleted_at')
            ->groupBy('seats.sector_name')
            ->selectRaw("COALESCE(seats.sector_name, 'Sem setor') as sector_name, COUNT(*) as sold_seats")
            ->pluck('sold_seats', 'sector_name');

        $heldBySector = DB::table('seats')
            ->join('inventory_hold_items', 'inventory_hold_items.seat_id', '=', 'seats.id')
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_items.inventory_hold_id')
            ->where('seats.venue_map_version_id', $event->venue_map_version_id)
            ->where('inventory_holds.tenant_id', $tenantId)
            ->where('inventory_holds.status', 'reservado')
            ->where('inventory_holds.expires_at', '>', now())
            ->groupBy('seats.sector_name')
            ->selectRaw("COALESCE(seats.sector_name, 'Sem setor') as sector_name, COUNT(*) as held_seats")
            ->pluck('held_seats', 'sector_name');

        $sectors = DB::table('seats')
            ->where('venue_map_version_id', $event->venue_map_version_id)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->groupBy('sector_name', 'status')
            ->selectRaw("COALESCE(sector_name, 'Sem setor') as sector_name, status, COUNT(*) as seat_count")
            ->get()
            ->groupBy('sector_name')
            ->map(function ($rows, $sectorName) use ($soldBySector, $heldBySector) {
                $total = (int) $rows->sum('seat_count');
                $blocked = (int) $rows->whereIn('status', ['bloqueado', 'indisponivel'])->sum('seat_count');
                $sold = (int) ($soldBySector[$sectorName] ?? 0);
                $held = (int) ($heldBySector[$sectorName] ?? 0);
                $available = max(0, $total - $sold - $blocked - $held);

                return [
                    'sector_name' => $sectorName,
                    'total_seats' => $total,
                    'sold_seats' => $sold,
                    'held_seats_now' => $held,
                    'blocked_seats' => $blocked,
                    'available_seats' => $available,
                    'occupancy_percentage' => $this->percentage($sold, $total),
                ];
            })
            ->values()
            ->sortByDesc('occupancy_percentage')
            ->values()
            ->all();

        return [
            'event_uuid' => $eventUuid,
            'event_name' => $event->name,
            'has_seat_map' => true,
            'totals' => [
                'total_seats' => array_sum(array_column($sectors, 'total_seats')),
                'sold_seats' => array_sum(array_column($sectors, 'sold_seats')),
                'held_seats_now' => array_sum(array_column($sectors, 'held_seats_now')),
                'blocked_seats' => array_sum(array_column($sectors, 'blocked_seats')),
                'available_seats' => array_sum(array_column($sectors, 'available_seats')),
                'occupancy_percentage' => $this->percentage(
                    array_sum(array_column($sectors, 'sold_seats')),
                    array_sum(array_column($sectors, 'total_seats'))
                ),
            ],
            'sectors' => $sectors,
        ];
    }

    /**
     * Comparação entre eventos (roadmap Fase A2, último item do relatório
     * 1 tela 2) — curva de vendas de 2 a `MAX_EVENTS` eventos do MESMO
     * tenant, indexada por "dia N desde a abertura de vendas" de cada
     * evento (regra de negócio confirmada pelo usuário), NÃO por data de
     * calendário. Isso permite comparar, por exemplo, o dia 5 de pré-venda
     * do Evento A com o dia 5 de pré-venda do Evento B mesmo que tenham
     * aberto em datas de calendário completamente diferentes.
     *
     * Definição de "abertura de vendas" de um evento (decisão registrada
     * aqui e em metrics-catalog.md, pois não existe uma data única no
     * schema): MENOR `ticket_types.sales_start_at` (não nulo) entre os
     * tipos de ingresso ativos do evento. Quando NENHUM tipo de ingresso
     * tem `sales_start_at` preenchido (venda sempre aberta, sem janela
     * configurada), usa-se o `created_at` do evento como fallback — é o
     * primeiro instante em que a venda poderia ter ocorrido.
     *
     * @param  array<int, string>  $eventUuids
     */
    public function compareEvents(int $tenantId, array $eventUuids): array
    {
        $eventUuids = array_values(array_unique($eventUuids));

        $events = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->whereIn('uuid', $eventUuids)
            ->whereNull('deleted_at')
            ->get(['id', 'uuid', 'name', 'created_at']);

        if ($events->count() !== count($eventUuids)) {
            throw (new ModelNotFoundException)->setModel(Event::class);
        }

        $eventsByUuid = $events->keyBy('uuid');

        $result = [];

        foreach ($eventUuids as $eventUuid) {
            $event = $eventsByUuid[$eventUuid];
            $result[] = $this->compareEventsBuildEntry($tenantId, $event);
        }

        return ['events' => $result];
    }

    private function compareEventsBuildEntry(int $tenantId, object $event): array
    {
        $minSalesStartAt = DB::table('ticket_types')
            ->where('event_id', $event->id)
            ->whereNull('deleted_at')
            ->whereNotNull('sales_start_at')
            ->min('sales_start_at');

        $salesOpenedAt = $minSalesStartAt
            ? Carbon::parse($minSalesStartAt)->startOfDay()
            : Carbon::parse($event->created_at)->startOfDay();

        $dailyRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->where('ticket_types.event_id', $event->id)
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->groupByRaw('DATE(sales.created_at)')
            ->selectRaw('DATE(sales.created_at) as sale_date, COUNT(DISTINCT sales.id) as order_count, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as revenue')
            ->orderByRaw('DATE(sales.created_at)')
            ->get();

        $series = [];
        foreach ($dailyRows as $row) {
            $dayIndex = (int) max(0, floor(
                (Carbon::parse($row->sale_date)->startOfDay()->timestamp - $salesOpenedAt->timestamp) / 86400
            ));

            if (! isset($series[$dayIndex])) {
                $series[$dayIndex] = ['day' => $dayIndex, 'order_count' => 0, 'quantity_sold' => 0, 'revenue' => 0.0];
            }

            $series[$dayIndex]['order_count'] += (int) $row->order_count;
            $series[$dayIndex]['quantity_sold'] += (int) $row->quantity_sold;
            $series[$dayIndex]['revenue'] += (float) $row->revenue;
        }

        ksort($series);
        $series = array_values(array_map(fn (array $point) => [
            'day' => $point['day'],
            'order_count' => $point['order_count'],
            'quantity_sold' => $point['quantity_sold'],
            'revenue' => $this->formatMoney($point['revenue']),
        ], $series));

        $totalOrders = array_sum(array_column($series, 'order_count'));
        $totalQuantity = array_sum(array_column($series, 'quantity_sold'));
        $totalRevenue = array_sum(array_map('floatval', array_column($series, 'revenue')));

        $capacity = (int) DB::table('ticket_types')
            ->where('event_id', $event->id)
            ->whereNull('deleted_at')
            ->whereNotNull('quantity_available')
            ->sum('quantity_available');

        $issued = (int) DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where('ticket_types.event_id', $event->id)
            ->where('tickets.tenant_id', $tenantId)
            ->whereNull('tickets.deleted_at')
            ->where('tickets.status', 'ativo')
            ->whereNotNull('ticket_types.quantity_available')
            ->whereNull('ticket_types.deleted_at')
            ->count();

        return [
            'event_uuid' => $event->uuid,
            'event_name' => $event->name,
            'sales_opened_at' => $salesOpenedAt->toDateString(),
            'totals' => [
                'total_orders' => $totalOrders,
                'total_quantity_sold' => $totalQuantity,
                'total_revenue' => $this->formatMoney($totalRevenue),
                'average_ticket' => $this->formatMoney($totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0),
                'tickets_issued' => $issued,
                'commercial_capacity' => $capacity,
                'occupancy_percentage' => $this->percentage($issued, $capacity),
            ],
            'series' => $series,
        ];
    }

    /**
     * Relatório de antifraude (roadmap Fase A3) — consome `sales.risk_flagged`/
     * `risk_reason` já persistidos por `RiskEngineService` (motor não é
     * alterado aqui, só lido). Contagem de vendas flagadas por período/
     * heurística, taxa sobre vendas pagas, e lista das vendas flagadas
     * para revisão manual do staff.
     *
     * `risk_reason` é texto renderizado (`__('messages.sale.risk_*_reason',
     * [...])`, com `:count`/`:hours`/`:minutes` já interpolados) — não uma
     * chave estável. A quebra por heurística usa `classifyRiskReason()`,
     * que casa um trecho fixo (não-interpolado) de cada mensagem; é
     * melhor-esforço e quebra se o texto das 6 mensagens em
     * `messages.sale.risk_*_reason` mudar sem atualizar os trechos aqui —
     * não há chave estruturada persistida (fora de escopo: exigiria
     * migration/alteração do `RiskEngineService`, que esta fase não toca).
     */
    public function riskReport(int $tenantId, ?string $from, ?string $to, int $limit = 50): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $flaggedQuery = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->where('sales.risk_flagged', true);

        $flaggedTotals = (clone $flaggedQuery)
            ->selectRaw('COUNT(*) as flagged_count, SUM(sales.total_amount) as flagged_amount')
            ->first();

        $paidSalesCount = (int) $this->salesQuery($tenantId, $fromDate, $toDate)
            ->where('sales.is_paid', true)
            ->count();

        $reasons = (clone $flaggedQuery)
            ->whereNotNull('sales.risk_reason')
            ->pluck('sales.risk_reason');

        $byHeuristic = [];
        foreach ($reasons as $reason) {
            $key = $this->classifyRiskReason($reason);
            $byHeuristic[$key] = ($byHeuristic[$key] ?? 0) + 1;
        }
        arsort($byHeuristic);

        $flaggedSales = (clone $flaggedQuery)
            ->leftJoin('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->orderByDesc('sales.created_at')
            ->limit($limit)
            ->selectRaw('sales.uuid as sale_uuid, sales.total_amount, sales.created_at, sales.is_paid, sales.risk_reason, final_customers.name as client_name')
            ->get()
            ->map(fn ($row) => [
                'sale_uuid' => $row->sale_uuid,
                'client_name' => $row->client_name,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
                'is_paid' => (bool) $row->is_paid,
                'created_at' => Carbon::parse($row->created_at)->toDateTimeString(),
                'heuristic' => $row->risk_reason ? $this->classifyRiskReason($row->risk_reason) : null,
                'risk_reason' => $row->risk_reason,
            ])
            ->all();

        $flaggedCount = (int) ($flaggedTotals->flagged_count ?? 0);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'totals' => [
                'flagged_count' => $flaggedCount,
                'flagged_amount' => $this->formatMoney((float) ($flaggedTotals->flagged_amount ?? 0)),
                'paid_sales_count' => $paidSalesCount,
                'flagged_rate_percentage' => $this->percentage($flaggedCount, $paidSalesCount),
            ],
            'by_heuristic' => array_map(
                fn (string $key, int $count) => ['heuristic' => $key, 'count' => $count],
                array_keys($byHeuristic),
                array_values($byHeuristic)
            ),
            'flagged_sales' => $flaggedSales,
        ];
    }

    private const RISK_HEURISTIC_PATTERNS = [
        'compras pagas para o mesmo evento' => 'compras_repetidas',
        'tentativas de pagamento recusadas/falhas' => 'velocity_pagamento_recusado',
        'cartão usado nesta compra foi usado por' => 'cartao_compartilhado',
        'cartões diferentes nos últimos' => 'multiplos_cartoes',
        'mesmo IP nos últimos' => 'velocity_ip',
        'reembolsos registrados nos últimos' => 'abuso_reembolso',
    ];

    private function classifyRiskReason(string $reason): string
    {
        foreach (self::RISK_HEURISTIC_PATTERNS as $needle => $key) {
            if (str_contains($reason, $needle)) {
                return $key;
            }
        }

        return 'outro';
    }

    /**
     * Relatório de revenda/transferência (roadmap Fase A3) — consome
     * `ticket_resale_listings` (revenda oficial) e `audit_logs` (evento
     * `ticket_transferred`, disparado por `TicketService::transfer()`) já
     * existentes. Volume/valor de revenda, status de repasse ao vendedor
     * (pendente/liberado) e transferências simples (sem revenda) por
     * período.
     *
     * "Transferência simples" não tem tabela própria — `TicketService::
     * transfer()` é chamado tanto pela troca de titular direta (staff/
     * Portal) quanto por dentro de `TicketResaleService::purchase()`
     * (fechamento da revenda). A distinção usada aqui é por EXCLUSÃO:
     * total de eventos `ticket_transferred` no período MENOS o total de
     * revendas `vendido` no período (cada revenda gera exatamente 1
     * evento de transferência) = transferências simples.
     *
     * `audit_logs` NÃO tem `tenant_id` (tabela técnica/global, ver
     * `HelpRequestService::buildDiagnostics()`) — o recorte por tenant
     * aqui é EXATO (não aproximado): `meta.ticket_uuid` de cada evento
     * `ticket_transferred` é cruzado com `tickets.tenant_id` via
     * `countTenantTicketTransfers()`, nunca contando evento de outro
     * tenant.
     */
    public function resaleReport(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);
        $range = [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()];

        $listingsQuery = DB::table('ticket_resale_listings')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        $listedCount = (int) (clone $listingsQuery)
            ->whereBetween('created_at', $range)
            ->count();

        $soldQuery = (clone $listingsQuery)
            ->where('status', 'vendido')
            ->whereBetween('sold_at', $range);

        $soldTotals = (clone $soldQuery)
            ->selectRaw('COUNT(*) as sold_count, SUM(asking_price) as total_transacted_amount, SUM(seller_payout_amount) as total_payout_amount')
            ->first();

        $payoutByStatus = (clone $soldQuery)
            ->groupBy('seller_payout_status')
            ->selectRaw('seller_payout_status, COUNT(*) as count, SUM(seller_payout_amount) as total_amount')
            ->get()
            ->keyBy('seller_payout_status');

        $payoutGroup = fn (string $status) => [
            'count' => (int) ($payoutByStatus[$status]->count ?? 0),
            'total_amount' => $this->formatMoney((float) ($payoutByStatus[$status]->total_amount ?? 0)),
        ];

        $cancelledCount = (int) (clone $listingsQuery)
            ->where('status', 'cancelado')
            ->whereBetween('cancelled_at', $range)
            ->count();

        $totalTransfersCount = $this->countTenantTicketTransfers($tenantId, $range);

        $resaleTransfersCount = (int) ($soldTotals->sold_count ?? 0);
        $simpleTransfersCount = max(0, $totalTransfersCount - $resaleTransfersCount);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'resale' => [
                'listed_count' => $listedCount,
                'sold_count' => $resaleTransfersCount,
                'cancelled_count' => $cancelledCount,
                'total_transacted_amount' => $this->formatMoney((float) ($soldTotals->total_transacted_amount ?? 0)),
                'total_payout_amount' => $this->formatMoney((float) ($soldTotals->total_payout_amount ?? 0)),
            ],
            'payout_by_status' => [
                'pendente_liberacao' => $payoutGroup('pendente_liberacao'),
                'liberado' => $payoutGroup('liberado'),
            ],
            'transfers' => [
                'total_count' => $totalTransfersCount,
                'resale_count' => $resaleTransfersCount,
                'simple_count' => $simpleTransfersCount,
            ],
        ];
    }

    /**
     * Conta eventos `ticket_transferred` de `audit_logs` cujo ticket
     * pertence ao tenant informado. `audit_logs` não tem `tenant_id`
     * (tabela global) nem FK pra `tickets` — o vínculo é via
     * `meta.ticket_uuid` (JSON, gravado por `AuditTicketTransferred`),
     * cruzado em PHP contra `tickets.uuid` filtrado por `tenant_id`. Só
     * lê os dois campos necessários (`created_at`/`meta`) da janela de
     * data, sem carregar a tabela inteira.
     *
     * @param  array{0: Carbon, 1: Carbon}  $range
     */
    private function countTenantTicketTransfers(int $tenantId, array $range): int
    {
        $ticketUuids = DB::table('audit_logs')
            ->where('event', 'ticket_transferred')
            ->whereBetween('created_at', $range)
            ->pluck('meta')
            ->map(function ($meta) {
                $decoded = is_string($meta) ? json_decode($meta, true) : null;

                return is_array($decoded) ? ($decoded['ticket_uuid'] ?? null) : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($ticketUuids === []) {
            return 0;
        }

        $tenantTicketUuids = DB::table('tickets')
            ->where('tenant_id', $tenantId)
            ->whereIn('uuid', array_unique($ticketUuids))
            ->pluck('uuid')
            ->flip();

        return count(array_filter($ticketUuids, fn (string $uuid) => $tenantTicketUuids->has($uuid)));
    }

    /**
     * Relatório de bilheteria por operador/terminal (roadmap Fase A3) —
     * consome `ticket_checkins` (`operator_id`/`gate_name`, controle de
     * acesso), `sales` (`created_by`, preenchido automaticamente por
     * `BaseModel` com `Auth::id()` — é o operador de balcão em vendas com
     * `origin` `staff`/`counter`) e `cash_sessions` (`opened_by`, sessão de
     * caixa presencial) já existentes. Sem tabela nova.
     */
    public function operatorReport(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $grantedCase = $this->caseWhenIn('ticket_checkins.result', self::CHECKIN_GRANTED_RESULTS);
        $warningCase = $this->caseWhenIn('ticket_checkins.result', self::CHECKIN_WARNING_RESULTS);
        $blockedCase = "CASE WHEN {$grantedCase} = 0 AND {$warningCase} = 0 THEN 1 ELSE 0 END";

        $byOperator = $this->ticketCheckinsQuery($tenantId, $fromDate, $toDate)
            ->leftJoin('users', 'users.id', '=', 'ticket_checkins.operator_id')
            ->groupBy('ticket_checkins.operator_id', 'users.name')
            ->selectRaw(
                "ticket_checkins.operator_id,
                COALESCE(users.name, 'Sem operador') as operator_name,
                COUNT(ticket_checkins.id) as total_reads,
                SUM({$grantedCase}) as granted_reads,
                SUM({$warningCase}) as warning_reads,
                SUM({$blockedCase}) as blocked_reads"
            )
            ->orderByDesc('total_reads')
            ->get()
            ->map(fn ($row) => [
                'operator_id' => $row->operator_id !== null ? (int) $row->operator_id : null,
                'operator_name' => $row->operator_name,
                'total_reads' => (int) $row->total_reads,
                'granted_reads' => (int) $row->granted_reads,
                'warning_reads' => (int) $row->warning_reads,
                'blocked_reads' => (int) $row->blocked_reads,
            ])
            ->all();

        $byGate = $this->ticketCheckinsQuery($tenantId, $fromDate, $toDate)
            ->groupBy('ticket_checkins.gate_name')
            ->selectRaw(
                "COALESCE(ticket_checkins.gate_name, 'Sem portaria') as gate_name,
                COUNT(ticket_checkins.id) as total_reads,
                SUM({$grantedCase}) as granted_reads,
                SUM({$warningCase}) as warning_reads,
                SUM({$blockedCase}) as blocked_reads"
            )
            ->orderByDesc('total_reads')
            ->get()
            ->map(fn ($row) => [
                'gate_name' => $row->gate_name,
                'total_reads' => (int) $row->total_reads,
                'granted_reads' => (int) $row->granted_reads,
                'warning_reads' => (int) $row->warning_reads,
                'blocked_reads' => (int) $row->blocked_reads,
            ])
            ->all();

        $salesByOperator = $this->salesQuery($tenantId, $fromDate, $toDate)
            ->whereIn('sales.origin', ['staff', 'counter'])
            ->leftJoin('users', 'users.id', '=', 'sales.created_by')
            ->groupBy('sales.created_by', 'users.name')
            ->selectRaw(
                "sales.created_by as operator_id,
                COALESCE(users.name, 'Sem operador') as operator_name,
                COUNT(sales.id) as sales_count,
                SUM(sales.total_amount) as total_amount"
            )
            ->orderByDesc('sales_count')
            ->get()
            ->map(fn ($row) => [
                'operator_id' => $row->operator_id !== null ? (int) $row->operator_id : null,
                'operator_name' => $row->operator_name,
                'sales_count' => (int) $row->sales_count,
                'total_amount' => $this->formatMoney((float) $row->total_amount),
            ])
            ->all();

        $cashSessionsByOperator = DB::table('cash_sessions')
            ->leftJoin('users', 'users.id', '=', 'cash_sessions.opened_by')
            ->where('cash_sessions.tenant_id', $tenantId)
            ->whereNull('cash_sessions.deleted_at')
            ->whereBetween('cash_sessions.opened_at', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()])
            ->groupBy('cash_sessions.opened_by', 'users.name')
            ->selectRaw(
                "cash_sessions.opened_by as operator_id,
                COALESCE(users.name, 'Sem operador') as operator_name,
                COUNT(*) as sessions_count,
                SUM(CASE WHEN cash_sessions.status = 'fechado' THEN 1 ELSE 0 END) as closed_sessions_count,
                SUM(cash_sessions.difference_amount) as total_difference_amount"
            )
            ->orderByDesc('sessions_count')
            ->get()
            ->map(fn ($row) => [
                'operator_id' => $row->operator_id !== null ? (int) $row->operator_id : null,
                'operator_name' => $row->operator_name,
                'sessions_count' => (int) $row->sessions_count,
                'closed_sessions_count' => (int) $row->closed_sessions_count,
                'total_difference_amount' => $this->formatMoney((float) ($row->total_difference_amount ?? 0)),
            ])
            ->all();

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'checkins_by_operator' => $byOperator,
            'checkins_by_gate' => $byGate,
            'sales_by_operator' => $salesByOperator,
            'cash_sessions_by_operator' => $cashSessionsByOperator,
        ];
    }

    /**
     * Coortes de retenção (roadmap Fase A3, parte 2) — agrupa clientes pelo
     * mês da primeira compra PAGA (`sales.is_paid=true`, `paid_at`) e mede
     * quantos voltaram a comprar em cada mês subsequente (M0..M6). SEM
     * tabela de snapshot (decisão do roadmap seção 5.3: começar em tempo
     * real com filtro obrigatório, só migrar pra pré-agregação se a
     * performance medida provar necessidade). `from` é OBRIGATÓRIO no
     * request (`AnalyticsCohortsRequest`) — é o filtro ativo exigido pela
     * regra transversal: sem mês de coorte escolhido, esta query nunca roda.
     *
     * M0 é sempre 100% por definição (a própria compra de aquisição conta
     * como retenção no mês 0). Meses ainda não decorridos desde a coorte
     * voltam com `retention_percentage=null` (não 0%) — coorte parcial,
     * não erro, conforme decisão do roadmap.
     */
    public function cohortsReport(int $tenantId, string $from, ?string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfMonth() : now()->endOfMonth();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfMonth(), $fromDate->copy()->endOfMonth()];
        }

        $firstPaidSub = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('is_paid', true)
            ->whereNotNull('paid_at')
            ->whereNotNull('final_customer_id')
            ->groupBy('final_customer_id')
            ->selectRaw('final_customer_id, MIN(paid_at) as first_paid_at');

        $monthExpr = $this->monthFormatExpression('first.first_paid_at');

        $cohortSizes = DB::table($firstPaidSub, 'first')
            ->whereBetween('first.first_paid_at', [$fromDate, $toDate])
            ->selectRaw("{$monthExpr} as cohort_month, COUNT(*) as cohort_size")
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr)
            ->get()
            ->keyBy('cohort_month');

        if ($cohortSizes->isEmpty()) {
            return [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'max_month_offset' => self::COHORT_MAX_MONTHS,
                'cohorts' => [],
            ];
        }

        $monthDiffExpr = $this->monthDiffExpression('s2.paid_at', 'first.first_paid_at');
        $monthAddExpr = $this->monthAddExpression('first.first_paid_at', self::COHORT_MAX_MONTHS);

        $retentionRows = DB::table($firstPaidSub, 'first')
            ->whereBetween('first.first_paid_at', [$fromDate, $toDate])
            ->join('sales as s2', function ($join) use ($tenantId, $monthAddExpr) {
                $join->on('s2.final_customer_id', '=', 'first.final_customer_id')
                    ->where('s2.tenant_id', $tenantId)
                    ->whereNull('s2.deleted_at')
                    ->whereNull('s2.cancelled_at')
                    ->where('s2.is_paid', true)
                    ->whereRaw('s2.paid_at >= first.first_paid_at')
                    ->whereRaw("s2.paid_at <= {$monthAddExpr}");
            })
            ->selectRaw("{$monthExpr} as cohort_month, {$monthDiffExpr} as month_offset, COUNT(DISTINCT first.final_customer_id) as retained_count")
            ->groupByRaw("{$monthExpr}, {$monthDiffExpr}")
            ->get();

        $retentionByMonth = [];
        foreach ($retentionRows as $row) {
            $retentionByMonth[$row->cohort_month][(int) $row->month_offset] = (int) $row->retained_count;
        }

        $now = now()->startOfMonth();
        $cohorts = [];
        foreach ($cohortSizes as $month => $row) {
            $cohortStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthsElapsed = min(self::COHORT_MAX_MONTHS, max(0, $cohortStart->diffInMonths($now)));
            $size = (int) $row->cohort_size;

            $retention = [];
            for ($offset = 0; $offset <= self::COHORT_MAX_MONTHS; $offset++) {
                if ($offset > $monthsElapsed) {
                    $retention[] = ['month_offset' => $offset, 'retained_count' => null, 'retention_percentage' => null];

                    continue;
                }

                $count = $retentionByMonth[$month][$offset] ?? 0;
                $retention[] = [
                    'month_offset' => $offset,
                    'retained_count' => $count,
                    'retention_percentage' => $this->percentage($count, $size),
                ];
            }

            $cohorts[] = [
                'cohort_month' => $month,
                'cohort_size' => $size,
                'retention' => $retention,
            ];
        }

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'max_month_offset' => self::COHORT_MAX_MONTHS,
            'cohorts' => array_values($cohorts),
        ];
    }

    /**
     * LTV histórico (roadmap Fase A3, parte 2) — valor total JÁ GASTO por
     * cliente até hoje (soma de `sales.total_amount` de venda ativa, mesma
     * convenção de receita do resto do módulo — não é só o valor pago),
     * agregado por segmento RFM de 8 níveis (reaproveita `RfmCalculator`,
     * nenhum recálculo de tercil) ou por coorte de aquisição (mês da
     * primeira compra PAGA, mesma definição de `cohortsReport()`). É
     * REALIZADO, não projeção — LTV previsto fica pra Fase A4 (exige
     * modelo). Sem período: LTV histórico é por natureza vitalício, não um
     * recorte de data — mesmo espírito de `churnClients()`, que também não
     * tem filtro de período.
     */
    public function ltvReport(int $tenantId, string $groupBy = 'segment'): array
    {
        $rows = $this->salesQuery($tenantId, null, null)
            ->join('final_customers', 'final_customers.id', '=', 'sales.final_customer_id')
            ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
            ->selectRaw(
                'final_customers.uuid as client_uuid,
                final_customers.name as client_name,
                COUNT(sales.id) as order_count,
                SUM(sales.total_amount) as ltv_amount,
                MAX(sales.created_at) as last_order_at,
                MIN(CASE WHEN sales.is_paid = 1 AND sales.paid_at IS NOT NULL THEN sales.paid_at END) as first_paid_at'
            )
            ->get();

        if ($rows->isEmpty()) {
            return [
                'group_by' => $groupBy,
                'overall' => ['customers_count' => 0, 'average_ltv' => $this->formatMoney(0)],
                'groups' => [],
            ];
        }

        $groups = $groupBy === 'cohort' ? $this->ltvByCohort($rows) : $this->ltvBySegment($rows);

        $totalLtv = (float) $rows->sum('ltv_amount');

        return [
            'group_by' => $groupBy,
            'overall' => [
                'customers_count' => $rows->count(),
                'average_ltv' => $this->formatMoney($totalLtv / $rows->count()),
            ],
            'groups' => $groups,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{key:string, label:string, customers_count:int, average_ltv:string, total_ltv:string}>
     */
    private function ltvBySegment($rows): array
    {
        $rfmInput = $rows->map(fn ($r) => [
            'recency_days' => (int) Carbon::parse($r->last_order_at)->startOfDay()->diffInDays(now()->startOfDay()),
            'frequency' => (int) $r->order_count,
            'monetary' => (float) $r->ltv_amount,
        ])->all();

        $segments8 = $this->rfmCalculator->segments8($rfmInput);

        $totals = [];
        foreach ($rows->values() as $index => $row) {
            $segment = $segments8[$index];
            $totals[$segment]['customers_count'] = ($totals[$segment]['customers_count'] ?? 0) + 1;
            $totals[$segment]['total_ltv'] = ($totals[$segment]['total_ltv'] ?? 0.0) + (float) $row->ltv_amount;
        }

        $groups = [];
        foreach ($totals as $segment => $agg) {
            $groups[] = [
                'key' => $segment,
                'label' => $this->rfmCalculator->displaySegment8Label($segment),
                'customers_count' => $agg['customers_count'],
                'average_ltv' => $this->formatMoney($agg['total_ltv'] / $agg['customers_count']),
                'total_ltv' => $this->formatMoney($agg['total_ltv']),
            ];
        }

        usort($groups, fn ($a, $b) => (float) $b['average_ltv'] <=> (float) $a['average_ltv']);

        return $groups;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{key:string, label:string, customers_count:int, average_ltv:string, total_ltv:string}>
     */
    private function ltvByCohort($rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            if ($row->first_paid_at === null) {
                continue;
            }

            $month = Carbon::parse($row->first_paid_at)->format('Y-m');
            $totals[$month]['customers_count'] = ($totals[$month]['customers_count'] ?? 0) + 1;
            $totals[$month]['total_ltv'] = ($totals[$month]['total_ltv'] ?? 0.0) + (float) $row->ltv_amount;
        }

        ksort($totals);

        $groups = [];
        foreach ($totals as $month => $agg) {
            $groups[] = [
                'key' => $month,
                'label' => $month,
                'customers_count' => $agg['customers_count'],
                'average_ltv' => $this->formatMoney($agg['total_ltv'] / $agg['customers_count']),
                'total_ltv' => $this->formatMoney($agg['total_ltv']),
            ];
        }

        return $groups;
    }

    /**
     * Afinidade entre eventos (roadmap Fase A3, parte 2) — para o evento
     * selecionado (filtro obrigatório, sem evento não roda), lista os top
     * `limit` eventos mais comprados pelos MESMOS clientes (contagem de
     * clientes em comum), útil pra cross-sell. Sem matriz completa nem
     * rede/grafo visual (decisão adiada, roadmap seção 5.4) — só ranking.
     */
    public function eventAffinityReport(int $tenantId, string $eventUuid, int $limit = 10): array
    {
        $event = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('uuid', $eventUuid)
            ->whereNull('deleted_at')
            ->first();

        if (! $event) {
            throw (new ModelNotFoundException)->setModel(Event::class);
        }

        $baseCustomers = DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->where('ticket_types.event_id', $event->id)
            ->whereNotNull('sales.final_customer_id')
            ->distinct()
            ->pluck('sales.final_customer_id');

        if ($baseCustomers->isEmpty()) {
            return [
                'event_uuid' => $eventUuid,
                'event_name' => $event->name,
                'base_customers_count' => 0,
                'affinities' => [],
            ];
        }

        $baseCustomersCount = $baseCustomers->count();

        $affinities = DB::table('sales')
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('ticket_types', 'ticket_types.id', '=', 'sale_items.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->where('ticket_types.event_id', '!=', $event->id)
            ->whereIn('sales.final_customer_id', $baseCustomers)
            ->groupBy('events.id', 'events.uuid', 'events.name')
            ->selectRaw('events.uuid as event_uuid, events.name as event_name, COUNT(DISTINCT sales.final_customer_id) as shared_customers_count')
            ->orderByDesc('shared_customers_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'event_uuid' => $row->event_uuid,
                'event_name' => $row->event_name,
                'shared_customers_count' => (int) $row->shared_customers_count,
                'affinity_percentage' => $this->percentage((int) $row->shared_customers_count, $baseCustomersCount),
            ])
            ->all();

        return [
            'event_uuid' => $eventUuid,
            'event_name' => $event->name,
            'base_customers_count' => $baseCustomersCount,
            'affinities' => $affinities,
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
            ->when($from, fn ($q) => $q->whereDate('sales.created_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('sales.created_at', '<=', $to->toDateString()));
    }

    private function saleItemsQuery(int $tenantId, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->whereNull('sales.deleted_at')
            ->whereNull('sales.cancelled_at')
            ->whereNull('sale_items.deleted_at')
            ->when($from, fn ($q) => $q->whereDate('sales.created_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('sales.created_at', '<=', $to->toDateString()));
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
            ->map(fn ($row) => [
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
     * Data formatada como 'YYYY-MM' — chave de mês usada em cohortsReport()/
     * ltvReport() (group_by=cohort).
     */
    private function monthFormatExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Diferença em MESES inteiros entre duas datas (a - b), usada em
     * cohortsReport() pra calcular o offset M0..M6 de cada linha.
     */
    private function monthDiffExpression(string $a, string $b): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "((CAST(strftime('%Y', {$a}) AS INTEGER) - CAST(strftime('%Y', {$b}) AS INTEGER)) * 12 + (CAST(strftime('%m', {$a}) AS INTEGER) - CAST(strftime('%m', {$b}) AS INTEGER)))"
            : "PERIOD_DIFF(DATE_FORMAT({$a}, '%Y%m'), DATE_FORMAT({$b}, '%Y%m'))";
    }

    /**
     * Soma N meses a uma coluna de data — usada em cohortsReport() pra
     * limitar o join da janela de retenção a COHORT_MAX_MONTHS.
     */
    private function monthAddExpression(string $column, int $months): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "datetime({$column}, '+{$months} months')"
            : "DATE_ADD({$column}, INTERVAL {$months} MONTH)";
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
    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function caseWhenIn(string $column, array $values): string
    {
        $quoted = implode(', ', array_map(
            fn (string $value) => DB::getPdo()->quote($value),
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
