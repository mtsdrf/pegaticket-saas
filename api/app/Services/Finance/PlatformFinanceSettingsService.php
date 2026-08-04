<?php

namespace App\Services\Finance;

use App\Models\Finance\PlatformFinanceSettings;
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
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): PlatformFinanceSettings
    {
        return DB::transaction(function () use ($attributes) {
            $settings = $this->getCurrent();
            $settings->fill($attributes);
            $settings->save();

            return $settings->fresh();
        });
    }
}
