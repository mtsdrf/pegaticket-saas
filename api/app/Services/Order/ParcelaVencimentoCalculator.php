<?php

namespace App\Services\Order;

use Carbon\Carbon;

/**
 * Calcula o vencimento de uma parcela (ou do pedido não parcelado):
 * dia configurado (`config('pegaticket.parcela_vencimento_dia')`) no mês
 * seguinte ao mês de referência informado.
 *
 * Se o dia configurado não existir no mês seguinte (ex.: configurar 31
 * num mês de 30 dias), usa o PRÓXIMO dia válido — o dia seguinte ao
 * último dia daquele mês (cai no mês seguinte a esse), não o último dia
 * do mês em si. Ver `.claude/memory/architecture-decisions.md`.
 *
 * Classe isolada (sem dependência de banco/tenant) para ser testável de
 * forma unitária, coberta para meses de 28/29/30/31 dias.
 */
class ParcelaVencimentoCalculator
{
    public function calculateDueDate(Carbon $referenceMonth): Carbon
    {
        $day = (int) config('pegaticket.parcela_vencimento_dia');

        $targetMonth = $referenceMonth->copy()->startOfMonth()->addMonthNoOverflow();

        if ($day <= $targetMonth->daysInMonth) {
            return $targetMonth->copy()->day($day)->startOfDay();
        }

        return $targetMonth->copy()->endOfMonth()->addDay()->startOfDay();
    }
}
