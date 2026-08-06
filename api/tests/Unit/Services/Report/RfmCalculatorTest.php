<?php

namespace Tests\Unit\Services\Report;

use App\Services\Report\RfmCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Roadmap Fase A3 — 8 segmentos nomeados da spec (seção 33), derivados
 * aditivamente da mesma combinação de tercis R/F/M já usada pelos 4
 * rótulos antigos (`segments()`/`label()`, INTOCADOS por esta fase).
 */
class RfmCalculatorTest extends TestCase
{
    #[Test]
    public function segments_still_returns_the_original_four_labels(): void
    {
        $calculator = new RfmCalculator;

        $clients = [
            ['recency_days' => 1, 'frequency' => 10, 'monetary' => 1000.0],
            ['recency_days' => 5, 'frequency' => 8, 'monetary' => 800.0],
            ['recency_days' => 10, 'frequency' => 5, 'monetary' => 500.0],
            ['recency_days' => 200, 'frequency' => 1, 'monetary' => 50.0],
        ];

        $segments = $calculator->segments($clients);

        foreach ($segments as $segment) {
            self::assertContains($segment, ['vip', 'recorrente', 'em_risco', 'inativo']);
        }
    }

    #[Test]
    public function segments8_covers_all_eight_named_segments_from_the_spec(): void
    {
        $calculator = new RfmCalculator;

        // 3 clientes por combinação de tercil garante 3 valores distintos
        // por dimensão (o tercileScorer precisa de >=3 pontos pra separar
        // 1/2/3), cobrindo as 27 combinações de R/F/M.
        $clients = [];
        foreach ([1, 2, 3] as $r) {
            foreach ([1, 2, 3] as $f) {
                foreach ([1, 2, 3] as $m) {
                    $clients[] = [
                        'recency_days' => (4 - $r) * 30,
                        'frequency' => $f,
                        'monetary' => $m * 100.0,
                    ];
                }
            }
        }

        $segments = $calculator->segments8($clients);

        $expectedSegments = [
            'campeoes', 'clientes_leais', 'potenciais_leais', 'novos_clientes',
            'promissores', 'precisam_atencao', 'em_risco8', 'inativos',
        ];

        foreach ($segments as $segment) {
            self::assertContains($segment, $expectedSegments);
        }

        // Todos os 8 segmentos aparecem no conjunto completo de 27 combinações.
        self::assertEqualsCanonicalizing($expectedSegments, array_values(array_unique($segments)));
    }

    #[Test]
    public function segments8_labels_best_recency_frequency_monetary_as_campeoes(): void
    {
        $calculator = new RfmCalculator;

        $clients = [
            ['recency_days' => 1, 'frequency' => 10, 'monetary' => 1000.0],
            ['recency_days' => 100, 'frequency' => 5, 'monetary' => 500.0],
            ['recency_days' => 300, 'frequency' => 1, 'monetary' => 10.0],
        ];

        $segments = $calculator->segments8($clients);

        self::assertSame('campeoes', $segments[0]);
        self::assertSame('inativos', $segments[2]);
    }

    #[Test]
    public function display_segment8_label_returns_portuguese_labels(): void
    {
        $calculator = new RfmCalculator;

        self::assertSame('Campeões', $calculator->displaySegment8Label('campeoes'));
        self::assertSame('Clientes leais', $calculator->displaySegment8Label('clientes_leais'));
        self::assertSame('Inativos', $calculator->displaySegment8Label('inativos'));
        self::assertSame('desconhecido', $calculator->displaySegment8Label('desconhecido'));
    }
}
