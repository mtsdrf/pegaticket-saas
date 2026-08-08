<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform_fee_fixed_amount' => ['required', 'numeric', 'min:0'],
            'default_settlement_offset_days' => ['required', 'integer', 'min:1', 'max:365'],
            'settlement_reference' => ['required', 'string', 'in:event_end'],
            'split_custody_enabled' => ['required', 'boolean'],
            'extra_reserve_enabled' => ['required', 'boolean'],
            'extra_reserve_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'extra_reserve_release_offset_days' => ['required', 'integer', 'min:1', 'max:365'],
            'pagbank_primary_account_id' => ['nullable', 'string', 'max:80'],
            'service_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_fee_minimum_amount' => ['required', 'numeric', 'min:0'],
            'estimated_pix_processing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estimated_card_processing_percentage_by_installment' => ['nullable', 'array'],
            'estimated_card_processing_percentage_by_installment.*' => ['numeric', 'min:0', 'max:100'],
        ];
    }
}
