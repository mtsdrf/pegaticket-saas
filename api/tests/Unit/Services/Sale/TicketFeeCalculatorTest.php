<?php

namespace Tests\Unit\Services\Sale;

use App\Services\Sale\TicketFeeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketFeeCalculatorTest extends TestCase
{
    private TicketFeeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new TicketFeeCalculator;
    }

    /**
     * @return array<string, array{0:int,1:int}>
     */
    public static function unitFeeProvider(): array
    {
        return [
            'gratuito' => [0, 0],
            'R$1 vira minimo' => [100, 300],
            'R$10 vira minimo' => [1000, 300],
            'R$20 vira minimo' => [2000, 300],
            'ponto de transicao abaixo (R$29,99)' => [2999, 300],
            'ponto de transicao exato (R$30,00)' => [3000, 300],
            'ponto de transicao acima (R$30,01), round ainda cai no minimo' => [3001, 300],
            'R$50 -> 10%' => [5000, 500],
            'R$100 -> 10%' => [10000, 1000],
            'R$999,99 -> 10% com round' => [99999, 10000],
        ];
    }

    #[Test]
    #[DataProvider('unitFeeProvider')]
    public function computes_unit_fee_with_percentage_and_floor(int $unitPriceCents, int $expectedFeeCents): void
    {
        $this->assertSame(
            $expectedFeeCents,
            $this->calculator->computeUnitFeeCents($unitPriceCents, 10, 300)
        );
    }

    #[Test]
    public function computes_line_fee_as_unit_fee_times_quantity_never_percentage_of_the_line_subtotal(): void
    {
        // 3 unidades de R$20 -> cada unidade cai no piso de R$3 (10% de
        // R$20 = R$2, abaixo do minimo) -> 3x R$3 = R$9, NUNCA 10% de
        // R$60 (que daria R$6).
        $this->assertSame(900, $this->calculator->computeLineFeeCents(2000, 3, 10, 300));
    }

    #[Test]
    public function suggests_unit_price_for_target_net_when_buyer_pays_the_fee(): void
    {
        // Comprador paga a taxa por cima: produtor recebe o valor cheio
        // pedido, o preço sugerido É o valor liquido alvo.
        $this->assertSame(10000, $this->calculator->suggestUnitPriceForTargetNetCents(10000, 10, 300, 'buyer'));
    }

    #[Test]
    public function suggests_unit_price_for_target_net_when_producer_pays_the_fee(): void
    {
        $price = $this->calculator->suggestUnitPriceForTargetNetCents(10000, 10, 300, 'producer');

        $this->assertSame(11111, $price);

        $net = $price - $this->calculator->computeUnitFeeCents($price, 10, 300);
        $this->assertGreaterThanOrEqual(10000, $net);
    }

    #[Test]
    public function suggests_unit_price_via_linear_search_when_the_closed_formula_lands_on_the_minimum_floor(): void
    {
        // Alvo liquido bem pequeno: a formula fechada tende a cair na
        // faixa dominada pelo piso minimo, nao pelo percentual.
        $price = $this->calculator->suggestUnitPriceForTargetNetCents(500, 10, 300, 'producer');

        $net = $price - $this->calculator->computeUnitFeeCents($price, 10, 300);
        $this->assertGreaterThanOrEqual(500, $net);
    }
}
