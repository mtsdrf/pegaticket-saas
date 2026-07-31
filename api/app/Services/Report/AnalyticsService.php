<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Analytics de vendas (Fase 1 do roadmap) — leitura agregada de
 * Order/OrderItem/OrderInstallment/Client, sem tabela própria.
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
 *   orders.created_at.
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

    /**
     * Dias sem venda a partir dos quais um produto com saldo em estoque é
     * considerado "encalhado" em stalledTicketTypes().
     */
    private const STALLED_INACTIVITY_DAYS = 60;

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
     * Top produtos por faturamento no período, via order_items
     * (snapshot de venda), com quantidade vendida.
     */
    public function topTicketTypes(int $tenantId, ?string $from, ?string $to, int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        return $this->orderItemsQuery($tenantId, $fromDate, $toDate)
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->whereNull('ticket_types.deleted_at')
            ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
            ->selectRaw('ticket_types.uuid as ticket_type_uuid, ticket_types.name as ticket_type_name, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total) as revenue')
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
     * Vendas por cidade E por bairro (pedido → client → endereco),
     * qtd + faturamento, ordenado por faturamento desc.
     */
    public function salesByLocation(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        return [
            'by_city' => $this->salesByLocationDimension($tenantId, $fromDate, $toDate, 'cidades', 'cidade_id'),
            'by_neighborhood' => $this->salesByLocationDimension($tenantId, $fromDate, $toDate, 'bairros', 'bairro_id'),
        ];
    }

    /**
     * Matriz ano × mês com TODOS os anos que têm pedido (sem filtro de
     * período — histórico completo por definição). Meses sem venda
     * entram zerados; anos em ordem decrescente.
     */
    public function salesHistory(int $tenantId): array
    {
        [$yearExpr, $monthExpr] = $this->yearMonthExpressions('orders.created_at');

        $rows = $this->ordersQuery($tenantId, null, null)
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, COUNT(*) as order_count, SUM(orders.total_amount) as revenue")
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

        $rows = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
                        ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
            ->selectRaw('final_customers.uuid as client_uuid, final_customers.name as client_name, COUNT(orders.id) as order_count, SUM(orders.total_amount) as total_amount, MAX(orders.created_at) as last_order_at')
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
     * Média de dias entre entrega (delivered_at) e pagamento (paid_at)
     * por cliente — só pedidos com os dois timestamps preenchidos —
     * ordenado do mais lento pro mais rápido.
     */
    public function paymentDelays(int $tenantId, ?string $from, ?string $to, int $limit = 10): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $avgDaysExpr = $this->dateDiffDaysExpression('orders.paid_at', 'orders.delivered_at');

        return $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
                        ->whereNotNull('orders.delivered_at')
            ->whereNotNull('orders.paid_at')
            ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
            ->selectRaw("final_customers.uuid as client_uuid, final_customers.name as client_name, AVG({$avgDaysExpr}) as avg_days_to_pay, COUNT(orders.id) as order_count")
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
     * Pedidos em atraso, paginado, ordenado por dias de atraso desc.
     * Um pedido gera no máximo uma linha por tipo:
     * - `pagamento`: parcela vencida não paga (agregado por pedido:
     *   valor em aberto = soma das parcelas vencidas não pagas, dias =
     *   maior atraso) OU pedido não parcelado com due_date vencido e
     *   não pago (valor em aberto = total_amount).
     * - `entrega`: expected_delivery_date estourado sem entrega; valor
     *   em aberto = total_amount se ainda não pago, 0 se já pago.
     * Um pedido atrasado em pagamento E entrega aparece duas vezes, uma
     * por tipo (decisão: os dois atrasos são acionáveis separadamente).
     */
    public function overdueOrders(int $tenantId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);
        $today = now()->toDateString();

        $overdueInstallments = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->join('order_installments', 'order_installments.order_id', '=', 'orders.id')
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
            ->whereNull('order_installments.deleted_at')
            ->where('order_installments.is_paid', false)
            ->whereDate('order_installments.due_date', '<', $today)
            ->groupBy('orders.id', 'orders.uuid', 'final_customers.name')
            ->selectRaw(
                "orders.uuid as order_uuid, final_customers.name as client_name, 'pagamento' as type, SUM(order_installments.amount) as open_amount, MAX("
                . $this->daysSinceExpression('order_installments.due_date')
                . ') as days_overdue',
                [$today]
            );

        $overduePaymentOrders = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
            ->where('orders.is_installment', false)
            ->where('orders.is_paid', false)
            ->whereNotNull('orders.due_date')
            ->whereDate('orders.due_date', '<', $today)
            ->selectRaw(
                "orders.uuid as order_uuid, final_customers.name as client_name, 'pagamento' as type, orders.total_amount as open_amount, "
                . $this->daysSinceExpression('orders.due_date')
                . ' as days_overdue',
                [$today]
            );

        $overdueDeliveries = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
            ->where('orders.is_delivered', false)
            ->whereNotNull('orders.expected_delivery_date')
            ->whereDate('orders.expected_delivery_date', '<', $today)
            ->selectRaw(
                "orders.uuid as order_uuid, final_customers.name as client_name, 'entrega' as type, CASE WHEN orders.is_paid = 1 THEN 0 ELSE orders.total_amount END as open_amount, "
                . $this->daysSinceExpression('orders.expected_delivery_date')
                . ' as days_overdue',
                [$today]
            );

        $union = $overdueInstallments
            ->unionAll($overduePaymentOrders)
            ->unionAll($overdueDeliveries);

        return DB::query()
            ->fromSub($union, 'overdue')
            ->orderByDesc('days_overdue')
            ->orderBy('order_uuid')
            ->paginate($perPage)
            ->through(fn($row) => [
                'order_uuid' => $row->order_uuid,
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
            $rows = $this->ordersQuery($tenantId, $fromDate, $toDate)
                ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
                                ->groupBy('final_customers.id', 'final_customers.uuid', 'final_customers.name')
                ->selectRaw('final_customers.uuid as uuid, final_customers.name as name, SUM(orders.total_amount) as revenue')
                ->orderByDesc('revenue')
                ->get();
        } else {
            $rows = $this->orderItemsQuery($tenantId, $fromDate, $toDate)
                ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
                ->whereNull('ticket_types.deleted_at')
                ->groupBy('ticket_types.id', 'ticket_types.uuid', 'ticket_types.name')
                ->selectRaw('ticket_types.uuid as uuid, ticket_types.name as name, SUM(order_items.line_total) as revenue')
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
     * Margem bruta do período a partir de order_items × custo de compra
     * do produto (last_purchase_cost). Só entra no cálculo de custo/margem
     * o item cujo produto tem custo conhecido; coverage_percentage revela
     * quanto do faturamento tem custo cadastrado (qualidade do dado).
     */
    public function marginSummary(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $row = $this->orderItemsQuery($tenantId, $fromDate, $toDate)
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->selectRaw(
                'SUM(order_items.line_total) as total_revenue, '
                . 'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN order_items.line_total ELSE 0 END) as revenue_with_cost, '
                . 'SUM(CASE WHEN ticket_types.last_purchase_cost IS NOT NULL THEN order_items.quantity * ticket_types.last_purchase_cost ELSE 0 END) as total_cost'
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

        $row = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->selectRaw(
                'SUM(CASE WHEN orders.coupon_id IS NOT NULL THEN 1 ELSE 0 END) as with_count, '
                . 'SUM(CASE WHEN orders.coupon_id IS NOT NULL THEN orders.total_amount ELSE 0 END) as with_total, '
                . 'SUM(CASE WHEN orders.coupon_id IS NULL THEN 1 ELSE 0 END) as without_count, '
                . 'SUM(CASE WHEN orders.coupon_id IS NULL THEN orders.total_amount ELSE 0 END) as without_total, '
                . 'SUM(CASE WHEN orders.coupon_id IS NOT NULL THEN orders.discount_amount ELSE 0 END) as total_discount'
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
            'orders_with_coupon' => [
                'count' => $withCount,
                'total_amount' => $this->formatMoney($withTotal),
                'average_ticket' => $this->formatMoney($avgWith),
            ],
            'orders_without_coupon' => [
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

        $totalRevenue = (float) $this->ordersQuery($tenantId, $fromDate, $toDate)->sum('orders.total_amount');

        $top10Revenue = (float) $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->whereNotNull('orders.final_customer_id')
            ->groupBy('orders.final_customer_id')
            ->selectRaw('SUM(orders.total_amount) as client_revenue')
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
     * OTIF (On Time In Full) de entrega: dos pedidos entregues com data
     * prevista, quantos foram entregues até a data prevista.
     */
    public function deliveryOtif(int $tenantId, ?string $from, ?string $to): array
    {
        [$fromDate, $toDate] = $this->resolvePeriod($from, $to);

        $row = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->where('orders.is_delivered', true)
            ->whereNotNull('orders.expected_delivery_date')
            ->selectRaw(
                'COUNT(*) as eligible, '
                . 'SUM(CASE WHEN DATE(orders.delivered_at) <= orders.expected_delivery_date THEN 1 ELSE 0 END) as on_time'
            )
            ->first();

        $eligible = (int) ($row->eligible ?? 0);
        $onTime = (int) ($row->on_time ?? 0);

        return [
            'eligible_orders' => $eligible,
            'on_time_orders' => $onTime,
            'otif_percentage' => $eligible > 0 ? round(($onTime / $eligible) * 100, 2) : 0.0,
        ];
    }

    /**
     * Clientes evadidos (churn): identificados por orders.final_customer_id, com
     * 2+ pedidos ativos e cujo último pedido é anterior a
     * CHURN_INACTIVITY_DAYS. Receita mensal em risco por cliente = soma
     * dos pedidos nos 90 dias que antecedem o último pedido, dividida por
     * 3 (média mensal daquela janela).
     */
    public function churnClients(int $tenantId): array
    {
        $threshold = now()->subDays(self::CHURN_INACTIVITY_DAYS)->startOfDay();

        $clients = $this->ordersQuery($tenantId, null, null)
            ->join('final_customers', 'final_customers.id', '=', 'orders.final_customer_id')
                        ->groupBy('final_customers.id', 'final_customers.name')
            ->havingRaw('COUNT(orders.id) >= 2')
            ->selectRaw('final_customers.id as client_id, final_customers.name as client_name, MAX(orders.created_at) as last_order_at')
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

        $orders = $this->ordersQuery($tenantId, null, null)
            ->whereIn('orders.final_customer_id', $clients->keys()->all())
            ->get(['orders.final_customer_id as client_id', 'orders.created_at', 'orders.total_amount']);

        $revenueAtRisk = [];

        foreach ($orders as $order) {
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
     * Produtos encalhados: saldo em estoque (> 0, somado entre locais) sem
     * nenhuma venda nos últimos STALLED_INACTIVITY_DAYS dias. value_tied_up
     * usa last_purchase_cost; quando ausente cai no price (cost_is_estimated
     * = true).
     */
    public function stalledTicketTypes(int $tenantId): array
    {
        $since = now()->subDays(self::STALLED_INACTIVITY_DAYS)->startOfDay();

        $soldProductIds = $this->orderItemsQuery($tenantId, $since, null)
            ->distinct()
            ->pluck('order_items.ticket_type_id')
            ->all();

        $rows = DB::table('stock_balances')
            ->join('ticket_types', 'ticket_types.id', '=', 'stock_balances.ticket_type_id')
            ->where('stock_balances.tenant_id', $tenantId)
            ->whereNull('stock_balances.deleted_at')
            ->whereNull('ticket_types.deleted_at')
            ->when($soldProductIds, fn($q) => $q->whereNotIn('stock_balances.ticket_type_id', $soldProductIds))
            ->groupBy('ticket_types.id', 'ticket_types.name', 'ticket_types.last_purchase_cost', 'ticket_types.price')
            ->havingRaw('SUM(stock_balances.quantity_on_hand) > 0')
            ->selectRaw('ticket_types.name as ticket_type_name, ticket_types.last_purchase_cost as last_purchase_cost, ticket_types.price as price, SUM(stock_balances.quantity_on_hand) as quantity_on_hand')
            ->get();

        $items = $rows->map(function ($row) {
            $quantityOnHand = (float) $row->quantity_on_hand;
            $costIsEstimated = $row->last_purchase_cost === null;
            $unitCost = $costIsEstimated ? (float) $row->price : (float) $row->last_purchase_cost;

            return [
                'ticket_type_name' => $row->ticket_type_name,
                'quantity_on_hand' => $quantityOnHand,
                'value_tied_up' => $quantityOnHand * $unitCost,
                'cost_is_estimated' => $costIsEstimated,
            ];
        });

        return [
            'items' => $items->sortByDesc('value_tied_up')
                ->take(15)
                ->map(fn($item) => [
                    'ticket_type_name' => $item['ticket_type_name'],
                    'quantity_on_hand' => $item['quantity_on_hand'],
                    'value_tied_up' => $this->formatMoney($item['value_tied_up']),
                    'cost_is_estimated' => $item['cost_is_estimated'],
                ])
                ->values()
                ->all(),
            'total_value_tied_up' => $this->formatMoney((float) $items->sum('value_tied_up')),
            'count' => $items->count(),
        ];
    }

    /**
     * Rupturas de estoque: produtos com saldo disponível (on_hand -
     * reserved - blocked, somado entre locais) <= 0 que tiveram ao menos
     * uma venda nos últimos 90 dias — ou seja, produto que vende e está
     * zerado. Ordenado pelas unidades vendidas nos 90 dias (mais crítico
     * primeiro).
     */
    public function stockRuptures(int $tenantId): array
    {
        $since = now()->subDays(90)->startOfDay();

        $sold = $this->orderItemsQuery($tenantId, $since, null)
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->whereNull('ticket_types.deleted_at')
            ->groupBy('ticket_types.id', 'ticket_types.name')
            ->selectRaw('ticket_types.id as ticket_type_id, ticket_types.name as ticket_type_name, SUM(order_items.quantity) as units_sold')
            ->get();

        if ($sold->isEmpty()) {
            return ['items' => [], 'count' => 0];
        }

        $available = DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('ticket_type_id', $sold->pluck('ticket_type_id')->all())
            ->groupBy('ticket_type_id')
            ->selectRaw('ticket_type_id, SUM(quantity_on_hand - quantity_reserved - quantity_blocked) as available')
            ->pluck('available', 'ticket_type_id');

        $items = $sold
            ->filter(fn($row) => (float) ($available[$row->ticket_type_id] ?? 0) <= 0)
            ->sortByDesc(fn($row) => (float) $row->units_sold)
            ->map(fn($row) => [
                'ticket_type_name' => $row->ticket_type_name,
                'units_sold_last_90_days' => (float) $row->units_sold,
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
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

        $dowExpr = $this->dayOfWeekExpression('orders.created_at');
        $hourExpr = $this->hourExpression('orders.created_at');

        $cells = $this->ordersQuery($tenantId, $fromDate, $toDate)
            ->selectRaw("{$dowExpr} as day_of_week, {$hourExpr} as hour, COUNT(*) as order_count, SUM(orders.total_amount) as revenue")
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

    // ------------------------------------------------------------------
    // Bases de query
    // ------------------------------------------------------------------

    private function ordersQuery(int $tenantId, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        return DB::table('orders')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->when($from, fn($q) => $q->whereDate('orders.created_at', '>=', $from->toDateString()))
            ->when($to, fn($q) => $q->whereDate('orders.created_at', '<=', $to->toDateString()));
    }

    private function orderItemsQuery(int $tenantId, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNull('orders.deleted_at')
            ->whereNull('orders.cancelled_at')
            ->whereNull('order_items.deleted_at')
            ->when($from, fn($q) => $q->whereDate('orders.created_at', '>=', $from->toDateString()))
            ->when($to, fn($q) => $q->whereDate('orders.created_at', '<=', $to->toDateString()));
    }

    private function salesSummaryPeriod(int $tenantId, Carbon $from, Carbon $to, string $groupBy): array
    {
        $bucketExpr = $this->bucketExpression($groupBy);

        $buckets = $this->ordersQuery($tenantId, $from, $to)
            ->selectRaw("{$bucketExpr} as bucket, COUNT(*) as order_count, SUM(orders.total_amount) as revenue")
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
            'total_orders' => $totalOrders,
            'total_revenue' => $this->formatMoney($totalRevenue),
            'average_ticket' => $this->formatMoney($totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0),
            'buckets' => $buckets,
        ];
    }

    private function salesByLocationDimension(int $tenantId, Carbon $from, Carbon $to, string $table, string $enderecoFk): array
    {
        return $this->ordersQuery($tenantId, $from, $to)
            ->join('final_customer_tenant_links', function ($join) {
                $join->on('final_customer_tenant_links.final_customer_id', '=', 'orders.final_customer_id')
                    ->on('final_customer_tenant_links.tenant_id', '=', 'orders.tenant_id');
            })
            ->join('enderecos', 'enderecos.id', '=', 'final_customer_tenant_links.endereco_id')
            ->join($table, "{$table}.id", '=', "enderecos.{$enderecoFk}")
            ->groupBy("{$table}.id", "{$table}.uuid", "{$table}.name")
            ->selectRaw("{$table}.uuid as uuid, {$table}.name as name, COUNT(*) as order_count, SUM(orders.total_amount) as revenue")
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($row) => [
                'uuid' => $row->uuid,
                'name' => $row->name,
                'order_count' => (int) $row->order_count,
                'revenue' => $this->formatMoney((float) $row->revenue),
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Expressões de data por driver (MySQL em produção, SQLite em teste)
    // ------------------------------------------------------------------

    private function bucketExpression(string $groupBy): string
    {
        $format = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%m';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', orders.created_at)"
            : "DATE_FORMAT(orders.created_at, '{$format}')";
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
}
