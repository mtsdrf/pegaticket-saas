<?php

namespace Tests\Concerns;

/**
 * `estados.uf` é único no banco. `strtoupper(Str::random(2))` tem só 36^2
 * combinações e colide de verdade quando um teste cria vários `Estado` na
 * mesma execução (achado em produção de CI, não hipotético — ver
 * `architecture-decisions.md`). Contador estático garante que nunca repete
 * dentro do processo do PHPUnit, independente de quantos `Estado` a suíte
 * inteira criar.
 */
trait GeneratesUniqueUf
{
    private static int $ufCounter = 0;

    protected function nextUf(): string
    {
        self::$ufCounter++;

        $first = chr(65 + intdiv(self::$ufCounter, 26) % 26);
        $second = chr(65 + self::$ufCounter % 26);

        return $first . $second;
    }
}
