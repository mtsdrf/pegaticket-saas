<?php

namespace App\Services\Finance;

use App\Services\Sale\TicketFeeCalculator;
use App\Support\Money;

/**
 * Simulador de precificação da taxa de serviço PegaTicket, usado pelo
 * tenant para conferir quanto o comprador paga / o produtor recebe antes
 * de publicar um preço de ingresso. Puramente leitura/cálculo — nunca
 * persiste nada, sempre usa a regra vigente (nunca um snapshot).
 */
class TicketFeeSimulationService
{
    public function __construct(
        private TicketFeeCalculator $calculator,
        private PlatformFinanceSettingsService $platformFinanceSettingsService,
    ) {}

    /**
     * @param  array{mode:string,amount:int,quantity?:int,fee_payer:string}  $data
     * @return array<string, mixed>
     */
    public function simulate(array $data): array
    {
        $rule = $this->platformFinanceSettingsService->getCurrentServiceFeeRule();
        $settings = $this->platformFinanceSettingsService->getCurrent();

        $mode = $data['mode'];
        $feePayer = $data['fee_payer'];
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $amountCents = (int) $data['amount'];

        $unitPriceCents = $mode === 'target_net'
            ? $this->calculator->suggestUnitPriceForTargetNetCents($amountCents, $rule['percentage'], $rule['minimum_cents'], $feePayer)
            : $amountCents;

        $unitFeeCents = $this->calculator->computeUnitFeeCents($unitPriceCents, $rule['percentage'], $rule['minimum_cents']);
        $lineFeeCents = $unitFeeCents * $quantity;

        $buyerPaysUnitCents = $feePayer === 'buyer' ? $unitPriceCents + $unitFeeCents : $unitPriceCents;
        $producerReceivesUnitCents = $feePayer === 'producer' ? $unitPriceCents - $unitFeeCents : $unitPriceCents;

        $effectivePercentage = $unitPriceCents > 0
            ? round(($unitFeeCents / $unitPriceCents) * 100, 2)
            : 0.0;

        return [
            'mode' => $mode,
            'fee_payer' => $feePayer,
            'quantity' => $quantity,
            'unit_price' => (float) Money::normalize($unitPriceCents / 100),
            'platform_fee_unit' => (float) Money::normalize($unitFeeCents / 100),
            'platform_fee_total' => (float) Money::normalize($lineFeeCents / 100),
            'buyer_pays_unit' => (float) Money::normalize($buyerPaysUnitCents / 100),
            'buyer_pays_total' => (float) Money::normalize(($buyerPaysUnitCents * $quantity) / 100),
            'producer_receives_unit' => (float) Money::normalize($producerReceivesUnitCents / 100),
            'producer_receives_total' => (float) Money::normalize(($producerReceivesUnitCents * $quantity) / 100),
            'effective_fee_percentage' => $effectivePercentage,
            'service_fee_rule_version' => $rule['version'],
            'payment_methods' => $this->buildEstimatedPaymentMethods($settings),
        ];
    }

    /**
     * @return array<int, array{method:string,installments:int,estimated_processing_percentage:?float,is_estimated:bool}>
     */
    private function buildEstimatedPaymentMethods($settings): array
    {
        $methods = [[
            'method' => 'pix',
            'installments' => 1,
            'estimated_processing_percentage' => $settings->estimated_pix_processing_percentage !== null
                ? (float) $settings->estimated_pix_processing_percentage
                : null,
            'is_estimated' => true,
        ]];

        $cardPercentages = $settings->estimated_card_processing_percentage_by_installment ?? [];

        for ($installments = 1; $installments <= 12; $installments++) {
            $methods[] = [
                'method' => 'card',
                'installments' => $installments,
                'estimated_processing_percentage' => isset($cardPercentages[(string) $installments])
                    ? (float) $cardPercentages[(string) $installments]
                    : null,
                'is_estimated' => true,
            ];
        }

        return $methods;
    }
}
