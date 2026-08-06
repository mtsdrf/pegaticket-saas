<?php

namespace App\Services\Report;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alertas básicos do Home (roadmap Fase A1, seção 88 do documento-base;
 * detecção de anomalias adicionada na Fase A4) — pagamentos, fila manual
 * e anomalia estatística simples sobre séries diárias. NÃO é o catálogo
 * completo de alertas configuráveis da spec (seção 79, fase futura) —
 * sem model, sem regra por tenant, tudo calculado on-the-fly a partir de
 * dado já existente, no mesmo espírito de `OperationSnapshotService`.
 */
class AlertService
{
    /**
     * Janela de observação da taxa de recusa de pagamento.
     */
    private const PAYMENT_REJECTION_WINDOW_DAYS = 7;

    private const PAYMENT_REJECTION_THRESHOLD_PERCENTAGE = 20.0;

    /**
     * Amostra mínima de vendas decididas (confirmed + rejected) na janela
     * para não disparar alerta com 1 recusa em 1 venda (100% de ruído).
     */
    private const PAYMENT_REJECTION_MIN_SAMPLE = 5;

    /**
     * Fila de aprovação pendente acima deste total dispara alerta.
     */
    private const PENDING_APPROVAL_THRESHOLD = 15;

    /**
     * Detecção de anomalia (Fase A4): mínimo de dias de histórico real do
     * tenant (desde a primeira venda) antes de sequer tentar avaliar —
     * desvio padrão calculado sobre poucos dias é estatisticamente pouco
     * confiável e gera falso positivo. Ver `.claude/memory/metrics-catalog.md`.
     */
    private const ANOMALY_MIN_HISTORY_DAYS = 7;

    /**
     * Janela de baseline usada pra calcular média/desvio padrão (excluindo
     * o dia mais recente, que é o dia avaliado). Limitada pelo histórico
     * real do tenant quando ele for mais curto que a janela.
     */
    private const ANOMALY_BASELINE_WINDOW_DAYS = 14;

    /**
     * Z-score simples (sem lib externa): dia mais recente a mais de N
     * desvios-padrão da média do baseline é sinalizado como anômalo.
     */
    private const ANOMALY_Z_SCORE_THRESHOLD = 2.0;

    /**
     * @return list<array{type: string, severity: string, title: string, message: string, meta: array<string, mixed>}>
     */
    public function activeAlerts(int $tenantId): array
    {
        return [
            ...$this->paymentRejectionAlert($tenantId),
            ...$this->pendingApprovalAlert($tenantId),
            ...$this->statisticalAnomalyAlerts($tenantId),
        ];
    }

    /**
     * Taxa de recusa de pagamento acima do limiar nos últimos N dias
     * (mesma definição de `AnalyticsService::paymentsSummary()`).
     */
    private function paymentRejectionAlert(int $tenantId): array
    {
        $from = now()->subDays(self::PAYMENT_REJECTION_WINDOW_DAYS)->startOfDay();

        $rows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $from)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        $confirmed = (int) ($rows['confirmed'] ?? 0);
        $rejected = (int) ($rows['rejected'] ?? 0);
        $decided = $confirmed + $rejected;

        if ($decided < self::PAYMENT_REJECTION_MIN_SAMPLE) {
            return [];
        }

        $rejectionRate = round(($rejected / $decided) * 100, 2);

        if ($rejectionRate < self::PAYMENT_REJECTION_THRESHOLD_PERCENTAGE) {
            return [];
        }

        return [[
            'type' => 'payment_rejection_rate',
            'severity' => $rejectionRate >= 40.0 ? 'critical' : 'warning',
            'title' => 'Taxa de recusa de pagamento alta',
            'message' => "{$rejectionRate}% das vendas decididas nos últimos ".self::PAYMENT_REJECTION_WINDOW_DAYS.' dias foram recusadas.',
            'meta' => [
                'window_days' => self::PAYMENT_REJECTION_WINDOW_DAYS,
                'confirmed_count' => $confirmed,
                'rejected_count' => $rejected,
                'rejection_rate_percentage' => $rejectionRate,
            ],
        ]];
    }

    /**
     * Fila de vendas aguardando aprovação acima do limiar — proxy simples
     * de "fila crescendo" (contagem absoluta atual, sem histórico de
     * tendência nesta fase).
     */
    private function pendingApprovalAlert(int $tenantId): array
    {
        $count = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'pending_approval')
            ->count();

        if ($count < self::PENDING_APPROVAL_THRESHOLD) {
            return [];
        }

        return [[
            'type' => 'payment_pending_queue',
            'severity' => $count >= self::PENDING_APPROVAL_THRESHOLD * 2 ? 'critical' : 'warning',
            'title' => 'Fila de aprovação de pagamento crescendo',
            'message' => "{$count} vendas aguardando aprovação manual.",
            'meta' => ['pending_approval_count' => $count],
        ]];
    }

    /**
     * Detecção estatística de anomalia (z-score, não machine learning)
     * sobre 3 séries diárias já existentes: receita, volume de vendas e
     * taxa de recusa de pagamento. Reaproveita `sales` (mesmo dado de
     * `AnalyticsService`/`paymentRejectionAlert`), não cria coleta nova.
     *
     * Baseline = últimos `ANOMALY_BASELINE_WINDOW_DAYS` dias (limitado ao
     * histórico real do tenant), dia avaliado = hoje. Sem baseline mínimo
     * (`ANOMALY_MIN_HISTORY_DAYS`), não avalia — evita falso positivo com
     * base estatística pequena demais.
     */
    private function statisticalAnomalyAlerts(int $tenantId): array
    {
        $today = now()->startOfDay();

        $firstSaleAt = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->min('created_at');

        if (! $firstSaleAt) {
            return [];
        }

        $firstSaleDate = Carbon::parse($firstSaleAt)->startOfDay();
        $earliestBaselineStart = $today->copy()->subDays(self::ANOMALY_BASELINE_WINDOW_DAYS);
        $baselineStart = $firstSaleDate->gt($earliestBaselineStart) ? $firstSaleDate : $earliestBaselineStart;

        $baselineDayCount = $baselineStart->diffInDays($today);

        if ($baselineDayCount < self::ANOMALY_MIN_HISTORY_DAYS) {
            return [];
        }

        $dayExpr = $this->dailyBucketExpression();

        $salesByDay = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $baselineStart)
            ->selectRaw("{$dayExpr} as day, COUNT(*) as sales_count, SUM(total_amount) as revenue")
            ->groupByRaw($dayExpr)
            ->get()
            ->keyBy('day');

        $decidedByDay = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', $baselineStart)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->selectRaw("{$dayExpr} as day, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected, COUNT(*) as decided")
            ->groupByRaw($dayExpr)
            ->get()
            ->keyBy('day');

        $revenueHistory = [];
        $salesCountHistory = [];
        $rejectionRateHistory = [];

        for ($cursor = $baselineStart->copy(); $cursor->lt($today); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $row = $salesByDay->get($key);
            $revenueHistory[] = (float) ($row->revenue ?? 0.0);
            $salesCountHistory[] = (float) ($row->sales_count ?? 0);

            $decided = $decidedByDay->get($key);
            if ($decided && (int) $decided->decided > 0) {
                $rejectionRateHistory[] = ((int) $decided->rejected / (int) $decided->decided) * 100;
            }
        }

        $todayKey = $today->toDateString();
        $todayRow = $salesByDay->get($todayKey);
        $todayRevenue = (float) ($todayRow->revenue ?? 0.0);
        $todaySalesCount = (float) ($todayRow->sales_count ?? 0);
        $todayDecided = $decidedByDay->get($todayKey);

        $alerts = [
            ...$this->buildAnomalyAlert(
                'daily_revenue_anomaly',
                'receita diária',
                $revenueHistory,
                $todayRevenue,
                fn (float $v) => 'R$ '.number_format($v, 2, ',', '.'),
            ),
            ...$this->buildAnomalyAlert(
                'daily_sales_count_anomaly',
                'volume de vendas diário',
                $salesCountHistory,
                $todaySalesCount,
                fn (float $v) => (string) (int) round($v),
            ),
        ];

        if ($todayDecided && (int) $todayDecided->decided > 0) {
            $todayRejectionRate = ((int) $todayDecided->rejected / (int) $todayDecided->decided) * 100;

            $alerts = [
                ...$alerts,
                ...$this->buildAnomalyAlert(
                    'daily_payment_rejection_rate_anomaly',
                    'taxa de recusa de pagamento diária',
                    $rejectionRateHistory,
                    $todayRejectionRate,
                    fn (float $v) => round($v, 2).'%',
                ),
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<float>  $history  valores diários do baseline (sem o dia avaliado)
     * @return list<array{type: string, severity: string, title: string, message: string, meta: array<string, mixed>}>
     */
    private function buildAnomalyAlert(string $type, string $metricLabel, array $history, float $latestValue, callable $formatter): array
    {
        $sampleSize = count($history);

        if ($sampleSize < self::ANOMALY_MIN_HISTORY_DAYS) {
            return [];
        }

        $mean = array_sum($history) / $sampleSize;
        $variance = array_sum(array_map(fn (float $v) => ($v - $mean) ** 2, $history)) / $sampleSize;
        $stdDev = sqrt($variance);

        // Baseline praticamente constante (desvio padrão ~0): z-score não é
        // confiável (divisão por quase-zero), então não avalia em vez de
        // disparar alerta ruidoso a qualquer variação mínima.
        if ($stdDev < 0.01) {
            return [];
        }

        $zScore = ($latestValue - $mean) / $stdDev;

        if (abs($zScore) < self::ANOMALY_Z_SCORE_THRESHOLD) {
            return [];
        }

        $direction = $zScore > 0 ? 'acima' : 'abaixo';
        $roundedZ = round(abs($zScore), 2);

        return [[
            'type' => $type,
            'severity' => abs($zScore) >= self::ANOMALY_Z_SCORE_THRESHOLD * 1.5 ? 'critical' : 'warning',
            'title' => 'Anomalia estatística detectada',
            'message' => "Hoje, {$metricLabel} está {$roundedZ} desvios-padrão {$direction} da média dos últimos {$sampleSize} dias ({$formatter($latestValue)} vs. média {$formatter($mean)}).",
            'meta' => [
                'metric' => $type,
                'history_days' => $sampleSize,
                'latest_value' => round($latestValue, 2),
                'mean' => round($mean, 2),
                'std_dev' => round($stdDev, 2),
                'z_score' => round($zScore, 2),
                'threshold' => self::ANOMALY_Z_SCORE_THRESHOLD,
            ],
        ]];
    }

    private function dailyBucketExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d', sales.created_at)"
            : 'DATE(sales.created_at)';
    }
}
