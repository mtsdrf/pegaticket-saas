<?php

namespace Tests\Unit\Services\Order;

use App\Services\Order\ParcelaVencimentoCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cobertura isolada da regra de vencimento (dia configurado no mês
 * seguinte à referência, com rollover para o próximo dia válido quando
 * o dia não existe no mês) — ver `.claude/memory/architecture-decisions.md`.
 */
class ParcelaVencimentoCalculatorTest extends TestCase
{
    private ParcelaVencimentoCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ParcelaVencimentoCalculator();
    }

    #[Test]
    public function uses_configured_day_when_it_exists_in_the_next_month(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 10);

        // Referência: janeiro (31 dias) -> mês seguinte: fevereiro.
        $due = $this->calculator->calculateDueDate(Carbon::create(2026, 1, 15));

        $this->assertSame('2026-02-10', $due->toDateString());
    }

    #[Test]
    public function rolls_over_to_the_next_month_when_day_does_not_exist_in_a_30_day_month(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 31);

        // Referência: março (31 dias) -> mês seguinte: abril (30 dias, sem dia 31).
        $due = $this->calculator->calculateDueDate(Carbon::create(2026, 3, 5));

        $this->assertSame('2026-05-01', $due->toDateString());
    }

    #[Test]
    public function rolls_over_to_the_next_month_when_day_does_not_exist_in_a_29_day_leap_february(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 30);

        // Referência: janeiro/2028 (ano bissexto) -> mês seguinte: fevereiro (29 dias).
        $due = $this->calculator->calculateDueDate(Carbon::create(2028, 1, 10));

        $this->assertSame('2028-03-01', $due->toDateString());
    }

    #[Test]
    public function uses_day_29_when_target_month_is_a_leap_february(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 29);

        // Referência: janeiro/2028 (ano bissexto) -> mês seguinte: fevereiro (29 dias, dia 29 existe).
        $due = $this->calculator->calculateDueDate(Carbon::create(2028, 1, 10));

        $this->assertSame('2028-02-29', $due->toDateString());
    }

    #[Test]
    public function rolls_over_to_the_next_month_when_day_does_not_exist_in_a_28_day_non_leap_february(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 29);

        // Referência: janeiro/2026 (não bissexto) -> mês seguinte: fevereiro (28 dias).
        $due = $this->calculator->calculateDueDate(Carbon::create(2026, 1, 10));

        $this->assertSame('2026-03-01', $due->toDateString());
    }

    #[Test]
    public function uses_configured_day_when_target_month_has_31_days(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 31);

        // Referência: abril (30 dias) -> mês seguinte: maio (31 dias, dia 31 existe).
        $due = $this->calculator->calculateDueDate(Carbon::create(2026, 4, 1));

        $this->assertSame('2026-05-31', $due->toDateString());
    }

    #[Test]
    public function advancing_the_reference_month_moves_the_due_date_one_month_forward(): void
    {
        Config::set('pegaticket.parcela_vencimento_dia', 10);

        $now = Carbon::create(2026, 1, 20);

        $installment1 = $this->calculator->calculateDueDate($now->copy()->addMonthsNoOverflow(0));
        $installment2 = $this->calculator->calculateDueDate($now->copy()->addMonthsNoOverflow(1));
        $installment3 = $this->calculator->calculateDueDate($now->copy()->addMonthsNoOverflow(2));

        $this->assertSame('2026-02-10', $installment1->toDateString());
        $this->assertSame('2026-03-10', $installment2->toDateString());
        $this->assertSame('2026-04-10', $installment3->toDateString());
    }
}
