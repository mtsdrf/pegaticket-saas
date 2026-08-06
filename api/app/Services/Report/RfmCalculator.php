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
     * 8 segmentos nomeados da spec (`pegaticket_indicadores_dashboards_relatorios.md`,
     * seção 33) — chave canônica em snake_case, rótulo em PT-BR. Ordem do
     * array reflete a ordem sugerida pela spec (melhor → pior), sem efeito
     * na lógica (só documentação).
     */
    private const SEGMENT8_LABELS = [
        'campeoes' => 'Campeões',
        'clientes_leais' => 'Clientes leais',
        'potenciais_leais' => 'Potenciais leais',
        'novos_clientes' => 'Novos clientes',
        'promissores' => 'Promissores',
        'precisam_atencao' => 'Precisam de atenção',
        'em_risco8' => 'Em risco',
        'inativos' => 'Inativos',
    ];

    /**
     * @param  array<int, array{recency_days:int, frequency:int, monetary:float}>  $clients
     * @return array<int, string> segmento (chave canônica: vip|recorrente|em_risco|inativo),
     *                            na mesma ordem/índice do array de entrada
     */
    public function segments(array $clients): array
    {
        return array_map(
            fn (array $scores) => $this->label($scores['r'], $scores['f'], $scores['m']),
            $this->scoreAll($clients)
        );
    }

    /**
     * Os 8 segmentos nomeados da spec (seção 33), derivados da MESMA
     * combinação de tercis R/F/M já usada em `segments()` — nenhuma query
     * nova, nenhum recálculo de tercil, só uma tabela de decisão mais fina
     * sobre o mesmo score. Aditivo: não substitui `segments()`/`label()`
     * (consumido hoje por Home/RfmChip), só oferece a granularidade que a
     * spec pede pra quem quiser consumir os 8 diretamente.
     *
     * @param  array<int, array{recency_days:int, frequency:int, monetary:float}>  $clients
     * @return array<int, string> chave canônica dos 8 segmentos, mesma ordem/índice de $clients
     */
    public function segments8(array $clients): array
    {
        return array_map(
            fn (array $scores) => $this->label8($scores['r'], $scores['f'], $scores['m']),
            $this->scoreAll($clients)
        );
    }

    /** Rótulo humano em PT-BR para exibição (o valor canônico já é o dado de verdade). */
    public function displayLabel(string $segment): string
    {
        return self::LABELS[$segment] ?? $segment;
    }

    /** Rótulo humano em PT-BR para um dos 8 segmentos de `segments8()`. */
    public function displaySegment8Label(string $segment): string
    {
        return self::SEGMENT8_LABELS[$segment] ?? $segment;
    }

    /**
     * @param  array<int, array{recency_days:int, frequency:int, monetary:float}>  $clients
     * @return array<int, array{r:int, f:int, m:int}> mesma ordem/índice de $clients
     */
    private function scoreAll(array $clients): array
    {
        $recencyScore = $this->tercileScorer(array_map(fn (array $c) => -$c['recency_days'], $clients));
        $frequencyScore = $this->tercileScorer(array_map(fn (array $c) => (float) $c['frequency'], $clients));
        $monetaryScore = $this->tercileScorer(array_map(fn (array $c) => $c['monetary'], $clients));

        return array_map(fn (array $client) => [
            'r' => $recencyScore(-$client['recency_days']),
            'f' => $frequencyScore((float) $client['frequency']),
            'm' => $monetaryScore($client['monetary']),
        ], $clients);
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
     * Mapeia a combinação de tercis R/F/M (1..3 cada, 27 combinações) para
     * os 8 segmentos nomeados da spec. Adaptação do modelo clássico de
     * segmentação RFM (normalmente feito sobre score 1..5) para tercis
     * (1..3) — regra decidida nesta implementação, não veio pronta da
     * spec (que só lista os 8 nomes, não a fórmula de corte):
     *
     * - `campeoes`: R=3, F=3, M=3 — o topo absoluto.
     * - `clientes_leais`: R>=2 e F=3 (resto de quem compra muito, fora dos
     *   campeões) — compra com frequência alta e continua ativo.
     * - `potenciais_leais`: R=3 e F=2 — recente e já com frequência média,
     *   candidato a virar leal.
     * - `promissores`: R=3, F=1, M>=2 — primeira(s) compra(s) recente(s)
     *   com ticket acima da mediana, sinal de potencial de gasto.
     * - `novos_clientes`: R=3, F=1, M=1 — recente, baixa frequência, baixo
     *   valor ainda — só entrou na base.
     * - `precisam_atencao`: R=2 (qualquer F=1 ou F=2) — nem recente nem
     *   antigo, engajamento mediano, precisa de estímulo pra não esfriar.
     * - `em_risco8`: R=1 e F>=2 — já foi engajado (comprou mais de uma
     *   vez) mas esfriou — mesma condição-base do `em_risco` de 4
     *   segmentos, aqui mantida com nome de chave distinto (`em_risco8`)
     *   só pra não colidir com a chave `em_risco` do array de 4 segmentos.
     * - `inativos`: R=1 e F=1 — cliente frio, compra única e distante.
     */
    private function label8(int $r, int $f, int $m): string
    {
        if ($r === 3 && $f === 3 && $m === 3) {
            return 'campeoes';
        }

        if ($r >= 2 && $f === 3) {
            return 'clientes_leais';
        }

        if ($r === 3 && $f === 2) {
            return 'potenciais_leais';
        }

        if ($r === 3 && $f === 1) {
            return $m >= 2 ? 'promissores' : 'novos_clientes';
        }

        if ($r === 2) {
            return 'precisam_atencao';
        }

        return $f >= 2 ? 'em_risco8' : 'inativos';
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
