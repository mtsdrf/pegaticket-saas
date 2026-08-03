<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vencimento de parcela de Venda
    |--------------------------------------------------------------------------
    | Dia do mês em que uma parcela (ou a venda não parcelado) vence, no
    | mês seguinte ao mês de referência. Ver App\Services\Order\ParcelaVencimentoCalculator
    | para a regra de rollover quando o dia não existe no mês.
    */
    'parcela_vencimento_dia' => (int) env('PARCELA_VENCIMENTO_DIA', 10),

];
