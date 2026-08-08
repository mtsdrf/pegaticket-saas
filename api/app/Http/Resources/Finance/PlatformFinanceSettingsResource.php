<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformFinanceSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'platform_fee_fixed_amount' => (float) $this->platform_fee_fixed_amount,
            'default_settlement_offset_days' => (int) $this->default_settlement_offset_days,
            'settlement_reference' => $this->settlement_reference,
            'split_custody_enabled' => (bool) $this->split_custody_enabled,
            'extra_reserve_enabled' => (bool) $this->extra_reserve_enabled,
            'extra_reserve_percentage' => (float) $this->extra_reserve_percentage,
            'extra_reserve_release_offset_days' => (int) $this->extra_reserve_release_offset_days,
            'pagbank_primary_account_id' => $this->pagbank_primary_account_id,
            'service_fee_percentage' => (float) $this->service_fee_percentage,
            'service_fee_minimum_amount' => (float) $this->service_fee_minimum_amount,
            'service_fee_rule_version' => (int) $this->service_fee_rule_version,
            'estimated_pix_processing_percentage' => $this->estimated_pix_processing_percentage !== null ? (float) $this->estimated_pix_processing_percentage : null,
            'estimated_card_processing_percentage_by_installment' => $this->estimated_card_processing_percentage_by_installment,
        ];
    }
}
