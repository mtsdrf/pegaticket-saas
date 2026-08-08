<?php

namespace App\Services\Finance;

use App\Models\Finance\PlatformFinanceSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PlatformFinanceSettingsService
{
    public function getCurrent(): PlatformFinanceSettings
    {
        return PlatformFinanceSettings::query()
            ->whereNull('deleted_at')
            ->first()
            ?? PlatformFinanceSettings::create([
                'platform_fee_fixed_amount' => 0,
                'default_settlement_offset_days' => 1,
                'settlement_reference' => 'event_end',
                'split_custody_enabled' => true,
                'extra_reserve_enabled' => false,
                // Default de reserva de risco (5%, liberada em D+30 após
                // disponibilidade do recebível) ainda não é uma decisão de
                // negócio validada com o usuário — só é aplicado quando um
                // admin ligar `extra_reserve_enabled` manualmente.
                'extra_reserve_percentage' => 5,
                'extra_reserve_release_offset_days' => 30,
                'pagbank_primary_account_id' => null,
                'service_fee_percentage' => 10.00,
                'service_fee_minimum_amount' => 3.00,
                'service_fee_rule_version' => 1,
                'estimated_pix_processing_percentage' => null,
                'estimated_card_processing_percentage_by_installment' => null,
            ]);
    }

    /**
     * Regra vigente da taxa de serviço PegaTicket (roadmap taxa 10%/mínimo
     * R$3) — chamado uma vez por operação (ex.: fora do loop de itens em
     * SaleService::create()), nunca por linha, para não repetir a mesma
     * query.
     *
     * @return array{percentage: float, minimum_cents: int, version: int}
     */
    public function getCurrentServiceFeeRule(): array
    {
        $settings = $this->getCurrent();

        return [
            'percentage' => (float) $settings->service_fee_percentage,
            'minimum_cents' => Money::toMinor((string) $settings->service_fee_minimum_amount),
            'version' => (int) $settings->service_fee_rule_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): PlatformFinanceSettings
    {
        return DB::transaction(function () use ($attributes) {
            $settings = $this->getCurrent();

            $previousPercentage = (string) $settings->service_fee_percentage;
            $previousMinimum = (string) $settings->service_fee_minimum_amount;

            $settings->fill($attributes);

            $percentageChanged = array_key_exists('service_fee_percentage', $attributes)
                && ! Money::equals($previousPercentage, (string) $settings->service_fee_percentage, 2);
            $minimumChanged = array_key_exists('service_fee_minimum_amount', $attributes)
                && ! Money::equals($previousMinimum, (string) $settings->service_fee_minimum_amount, 2);

            if ($percentageChanged || $minimumChanged) {
                $settings->service_fee_rule_version = ((int) $settings->service_fee_rule_version) + 1;
            }

            $settings->save();

            return $settings->fresh();
        });
    }
}
