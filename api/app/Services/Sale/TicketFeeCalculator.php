<?php

namespace App\Services\Sale;

use App\Support\Money;

/**
 * Regra de negócio da taxa de serviço PegaTicket (10% por ingresso, mínimo
 * R$3, valores configuráveis via PlatformFinanceSettings): calculada
 * SEMPRE por linha (unitário × quantidade), nunca percentual direto sobre
 * o subtotal do venda — ver `.claude/memory/api-patterns.md`.
 */
class TicketFeeCalculator
{
    public function computeUnitFeeCents(int $unitPriceCents, float $percentage, int $minimumCents): int
    {
        return Money::applyPercentWithFloor($unitPriceCents, $percentage, $minimumCents);
    }

    public function computeLineFeeCents(int $unitPriceCents, int $quantity, float $percentage, int $minimumCents): int
    {
        return $this->computeUnitFeeCents($unitPriceCents, $percentage, $minimumCents) * $quantity;
    }

    /**
     * Sugere um preço de ingresso unitário (em centavos) a partir de um
     * valor líquido alvo (o que o produtor quer efetivamente receber por
     * unidade), dado quem paga a taxa:
     * - `buyer`: o comprador paga a taxa por cima — o produtor já recebe o
     *   valor cheio, então o preço sugerido É o próprio valor líquido alvo.
     * - `producer`: a taxa sai do preço — resolve o preço tal que
     *   `preço - taxa(preço) = targetNet`.
     */
    public function suggestUnitPriceForTargetNetCents(
        int $targetNetCents,
        float $percentage,
        int $minimumCents,
        string $feePayer
    ): int {
        if ($feePayer === 'buyer') {
            return $targetNetCents;
        }

        if ($targetNetCents <= 0) {
            return 0;
        }

        // Fórmula fechada válida quando a taxa percentual (não o piso
        // mínimo) está em vigor no ponto de solução.
        $candidate = (int) round($targetNetCents / (1 - $percentage / 100));
        $fee = $this->computeUnitFeeCents($candidate, $percentage, $minimumCents);

        if ($candidate - $fee >= $targetNetCents) {
            return $candidate;
        }

        // O preço fechado caiu na faixa em que a taxa é dominada pelo piso
        // mínimo (não pelo percentual) — busca linear simples a partir do
        // menor preço plausível (targetNet + minimo), barato o bastante
        // para valores reais de ingresso.
        $price = $targetNetCents + $minimumCents;

        while (($price - $this->computeUnitFeeCents($price, $percentage, $minimumCents)) < $targetNetCents) {
            $price++;
        }

        return $price;
    }
}
