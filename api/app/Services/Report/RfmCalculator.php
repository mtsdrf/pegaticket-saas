<?php

namespace App\Services\Report;

/**
 * Fórmula única de RFM (recência/frequência/valor monetário), compartilhada
 * por ReportService::rfmClients() e AnalyticsService::topClients() — antes
 * cada service tinha sua própria regra (faixas fixas de dias vs. tercis),
 * violando a exigência da spec de que Home e relatórios usem a mesma
 * definição (ver docs/roadmap/2026-08-05-pegaticket-analytics-refactor-roadmap.md,
 * seções 5.1/5.2, e pegaticket_indicadores_dashboards_relatorios.md seção 33).
 *
 * Escolha: score relativo por tercil sobre a distribuição do próprio
 * conjunto de clientes analisado (não faixas fixas de dias/valor). A spec
 * define RFM como as três dimensões relativas entre si dentro do público do
 * tenant, não limiares absolutos — faixas fixas (ex. "VIP se comprou nos
 * últimos 30 dias") não se adaptam à realidade de cada operação (ex. um
 * tenant que só vende para eventos trimestrais nunca teria um único VIP) e
 * já geravam divergência de rótulo para o mesmo cliente entre Home e
 * Análises. É a fórmula que já estava em AnalyticsService::topClients().
 */
final class RfmCalculator
{
    private const LABELS = [
        'vip' => 'VIP',
        'recorrente' => 'Recorrente',
        'em_risco' => 'Em risco',
        'inativo' => 'Inativo',
    ];

    /**
     * @param  array<int, array{recency_days:int, frequency:int, monetary:float}>  $clients
     * @return array<int, string> segmento (chave canônica: vip|recorrente|em_risco|inativo),
     *                            na mesma ordem/índice do array de entrada
     */
    public function segments(array $clients): array
    {
        $recencyScore = $this->tercileScorer(array_map(fn (array $c) => -$c['recency_days'], $clients));
        $frequencyScore = $this->tercileScorer(array_map(fn (array $c) => (float) $c['frequency'], $clients));
        $monetaryScore = $this->tercileScorer(array_map(fn (array $c) => $c['monetary'], $clients));

        return array_map(function (array $client) use ($recencyScore, $frequencyScore, $monetaryScore) {
            $r = $recencyScore(-$client['recency_days']);
            $f = $frequencyScore((float) $client['frequency']);
            $m = $monetaryScore($client['monetary']);

            return $this->label($r, $f, $m);
        }, $clients);
    }

    /** Rótulo humano em PT-BR para exibição (o valor canônico já é o dado de verdade). */
    public function displayLabel(string $segment): string
    {
        return self::LABELS[$segment] ?? $segment;
    }

    /**
     * R = 1 (tercil menos recente): `inativo` se F = 1, senão `em_risco`
     * (bom cliente esfriando). R >= 2: `vip` se F = 3 e M = 3; `recorrente`
     * se F >= 2; senão `em_risco` (compra recente porém esporádica).
     */
    private function label(int $r, int $f, int $m): string
    {
        if ($r === 1) {
            return $f === 1 ? 'inativo' : 'em_risco';
        }

        if ($f === 3 && $m === 3) {
            return 'vip';
        }

        return $f >= 2 ? 'recorrente' : 'em_risco';
    }

    /**
     * @param  array<int, float>  $values
     */
    private function tercileScorer(array $values): \Closure
    {
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if ($count === 0) {
            return fn (float $value): int => 1;
        }

        $q1 = $sorted[(int) floor($count / 3)] ?? $sorted[0];
        $q2 = $sorted[(int) floor(($count * 2) / 3)] ?? $sorted[$count - 1];

        return fn (float $value): int => match (true) {
            $value >= $q2 => 3,
            $value >= $q1 => 2,
            default => 1,
        };
    }
}
